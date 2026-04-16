FROM ubuntu:22.04

LABEL maintainer="Taylor Otwell"

ARG WWWGROUP
ARG NODE_VERSION=22
ARG MYSQL_CLIENT="mysql-client"
ARG POSTGRES_VERSION=18

WORKDIR /var/www/html

ENV DEBIAN_FRONTEND=noninteractive
ENV TZ=UTC
ENV LANG=C.UTF-8
ENV SUPERVISOR_PHP_COMMAND="/usr/bin/php -d variables_order=EGPCS /var/www/html/artisan serve --host=0.0.0.0 --port=80"
ENV SUPERVISOR_PHP_USER="sail"
ENV PLAYWRIGHT_BROWSERS_PATH=0

# timezone
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# apt фиксы (важно для РФ)
RUN echo "Acquire::http::Pipeline-Depth 0;" > /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::http::No-Cache true;" >> /etc/apt/apt.conf.d/99custom && \
    echo "Acquire::BrokenProxy true;" >> /etc/apt/apt.conf.d/99custom && \
    echo 'Acquire::ForceIPv4 "true";' > /etc/apt/apt.conf.d/99force-ipv4

# 🔥 СРАЗУ меняем зеркала (до любого apt!)
RUN sed -i 's|http://archive.ubuntu.com/ubuntu|http://mirror.yandex.ru/ubuntu|g' /etc/apt/sources.list && \
    sed -i 's|http://security.ubuntu.com/ubuntu|http://mirror.yandex.ru/ubuntu|g' /etc/apt/sources.list

# базовые пакеты (через HTTP зеркало)
RUN apt-get update -o Acquire::Retries=5 \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gnupg \
    && rm -rf /var/lib/apt/lists/*

# теперь можно HTTPS
RUN sed -i 's|http://mirror.yandex.ru|https://mirror.yandex.ru|g' /etc/apt/sources.list

# основная установка
RUN apt-get update -o Acquire::Retries=5 \
    && mkdir -p /etc/apt/keyrings \
    && apt-get install -y \
        gosu zip unzip git supervisor sqlite3 libcap2-bin \
        libpng-dev python3 dnsutils librsvg2-bin fswatch nano \
        jpegoptim optipng pngquant gifsicle webp libwebp-dev \
        imagemagick libjpeg-turbo-progs ffmpeg \
    \
    # PHP repo
    && curl -fsSL 'https://keyserver.ubuntu.com/pks/lookup?op=get&search=0xb8dc7e53946656efbce4c1dd71daeaab4ad4cab6' \
        | gpg --dearmor -o /etc/apt/keyrings/ppa_ondrej_php.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/ppa_ondrej_php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu jammy main" \
        > /etc/apt/sources.list.d/ppa_ondrej_php.list \
    \
    && apt-get update -o Acquire::Retries=5 \
    && apt-get install -y \
        libgd3 php8.4-cli php8.4-dev \
        php8.4-pgsql php8.4-sqlite3 php8.4-gd \
        php8.4-curl php8.4-mongodb \
        php8.4-imap php8.4-mysql php8.4-mbstring \
        php8.4-xml php8.4-zip php8.4-bcmath php8.4-soap \
        php8.4-intl php8.4-readline php8.4-ldap \
        php8.4-msgpack php8.4-igbinary php8.4-redis php8.4-swoole \
        php8.4-memcached php8.4-pcov php8.4-imagick php8.4-xdebug \
    \
    # composer
    && curl -fsSL https://getcomposer.org/installer \
        | php -- --install-dir=/usr/bin/ --filename=composer \
    \
    # node
    && curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o /etc/apt/keyrings/nodesource.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/nodesource.gpg] https://deb.nodesource.com/node_$NODE_VERSION.x nodistro main" \
        > /etc/apt/sources.list.d/nodesource.list \
    \
    && apt-get update -o Acquire::Retries=5 \
    && apt-get install -y nodejs \
    \
    # playwright
    && npx playwright install-deps \
    && corepack enable \
    && corepack prepare yarn@stable --activate \
    \
    # postgres
    && curl -fsSL https://www.postgresql.org/media/keys/ACCC4CF8.asc \
        | gpg --dearmor -o /etc/apt/keyrings/pgdg.gpg \
    && echo "deb [signed-by=/etc/apt/keyrings/pgdg.gpg] http://apt.postgresql.org/pub/repos/apt jammy-pgdg main" \
        > /etc/apt/sources.list.d/pgdg.list \
    \
    && apt-get update -o Acquire::Retries=5 \
    && apt-get install -y $MYSQL_CLIENT postgresql-client-$POSTGRES_VERSION \
    \
    && apt-get autoremove -y \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# порт 80
RUN setcap "cap_net_bind_service=+ep" /usr/bin/php8.4

# пользователь
RUN userdel -r ubuntu || true
RUN groupadd --force -g $WWWGROUP sail
RUN useradd -ms /bin/bash --no-user-group -g $WWWGROUP -u 1337 sail

RUN git config --global --add safe.directory /var/www/html

# файлы
COPY start-container /usr/local/bin/start-container
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY php.ini /etc/php/8.4/cli/conf.d/99-sail.ini

RUN chmod +x /usr/local/bin/start-container

EXPOSE 80/tcp

ENTRYPOINT ["start-container"]
