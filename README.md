# Vibe API

API мессенджера на Laravel 12. В проекте используются PostgreSQL, Redis, Laravel Reverb и S3-совместимое хранилище. Веб-интерфейса пока нет, поэтому сборка Vite для запуска API не требуется.

## Требования

- Docker Engine с Docker Compose или Docker Desktop. В Windows включите интеграцию Docker Desktop с нужным дистрибутивом WSL 2 и выполняйте команды внутри WSL.
- Git. Для работы с приватным репозиторием и образами GHCR понадобятся соответствующие права доступа.

PHP и Composer на компьютере устанавливать не нужно: они запускаются в Docker. Текущий production-образ использует PHP 8.4.27; образ Sail при сборке проверяет, что версия PHP не ниже 8.4.27.

## Локальный запуск через Sail

Клонируйте репозиторий и перейдите в его каталог:

```bash
git clone https://github.com/s0loparty/vibeup-api.git
cd vibeup-api
```

Дальнейшие команды выполняются из корня репозитория. Если `.env` уже существует, сохраните его и не перезаписывайте.

1. Создайте файл окружения и настройте его:

   ```bash
   cp .env.example .env
   ```

   В `.env` замените настройки базы и Redis на следующие. Удалите или измените существующие строки с этими именами, чтобы каждая переменная была задана один раз:

   ```dotenv
   APP_URL=http://localhost
   DB_CONNECTION=pgsql
   DB_HOST=pgsql
   DB_PORT=5432
   DB_DATABASE=laravel
   DB_USERNAME=sail
   DB_PASSWORD=password

   REDIS_CLIENT=phpredis
   REDIS_HOST=redis
   REDIS_PASSWORD=null
   REDIS_PORT=6379
   QUEUE_CONNECTION=redis
   ```

   `DB_PASSWORD` должен быть непустым. Если порт 80 занят, задайте, например, `APP_PORT=8080` и соответствующий `APP_URL`. Изменение учётных данных PostgreSQL в `.env` не меняет пользователя и пароль уже созданной базы в томе Docker.

2. Установите Composer-зависимости через контейнер Sail. Это создаст `vendor/bin/sail` даже на свежем клоне:

   ```bash
   WWWUSER="$(id -u)" WWWGROUP="$(id -g)" docker compose run --rm --no-deps --build laravel.test composer install
   ```

3. Запустите сервисы, создайте ключ приложения и примените миграции:

   ```bash
   ./vendor/bin/sail up -d
   ./vendor/bin/sail artisan key:generate --no-interaction
   ./vendor/bin/sail restart queue
   ./vendor/bin/sail artisan migrate --no-interaction
   ```

   `key:generate` выполняется при первоначальной установке. Сохраняйте `APP_KEY`: его смена сделает ранее зашифрованные данные недоступными.

4. Для загрузки файлов настройте локальный MinIO. В `.env` укажите отдельные приватный и публичный бакеты:

   ```dotenv
   FILESYSTEM_DISK=minio
   MEDIA_DISK=minio
   AWS_ACCESS_KEY_ID=sail
   AWS_SECRET_ACCESS_KEY=password
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=user-media
   AWS_PUBLIC_BUCKET=user-media-public
   AWS_ENDPOINT=http://minio:9000
   AWS_URL=http://localhost:9000/user-media
   AWS_PUBLIC_URL=http://localhost:9000/user-media-public
   AWS_USE_PATH_STYLE_ENDPOINT=true
   ```

   После готовности MinIO создайте бакеты:

   ```bash
   ./vendor/bin/sail artisan minio:setup-buckets --no-interaction
   ```

   Команда устанавливает публичную политику чтения для `AWS_PUBLIC_BUCKET`. Не используйте для него имя приватного `AWS_BUCKET`. Учётные данные MinIO в локальном Compose заданы как `sail` / `password` и предназначены только для разработки.

По умолчанию API доступен на порту `80`, MinIO API — на `9000`, консоль MinIO — на `8900`, интерфейс Mailpit — на `8025`. Порты можно изменить в `.env` через `APP_PORT` и переменные `FORWARD_*` из `compose.yaml`; при смене порта MinIO обновите также `AWS_URL` и `AWS_PUBLIC_URL`.

Сервис `queue` автоматически запускает обработчик Redis и перезапускается вместе со стеком. Ручной запуск `queue:work` не нужен. После изменения `.env` перезапустите обработчик командой `./vendor/bin/sail restart queue`, чтобы он перечитал настройки. Локальный Compose не запускает и не публикует Reverb: для проверки WebSocket потребуется отдельно настроить и запустить сервер Reverb, его порт и `REVERB_*`.

Полезные команды:

```bash
./vendor/bin/sail ps
./vendor/bin/sail logs -f queue
./vendor/bin/sail artisan test --compact
./vendor/bin/sail down
```

После изменения Dockerfile пересоберите образ и пересоздайте контейнеры:

```bash
./vendor/bin/sail build --no-cache
./vendor/bin/sail up -d
```

Команда `down` сохраняет тома PostgreSQL, Redis и MinIO. Удаление томов приведёт к потере хранящихся в них данных.

## Развёртывание в Dokploy

### Публикация образов

После push в любую ветку, включая push после merge, GitHub Actions собирает и публикует образы `dokploy-app` и `dokploy-web` в GHCR. Workflow находится в `.github/workflows/dokploy-images.yml`; по умолчанию Compose использует репозиторий `ghcr.io/s0loparty/vibeup-api`.

Каждый образ получает тег ветки и `sha-<полный SHA коммита>`. Например, ветке `deploy/dokploy` соответствует тег `deploy-dokploy`. Тег `latest` обновляется только из `main`. Для точного соответствия образов одного коммита используйте SHA-тег.

Дождитесь успешного выполнения workflow перед первым развёртыванием. Для приватных пакетов предоставьте серверу Dokploy доступ на чтение обоих образов GHCR через настройки реестра или Docker. При использовании другого GitHub-репозитория задайте `IMAGE_REPOSITORY` с его адресом в GHCR.

### Создание сервиса

1. В Dokploy создайте сервис **Compose**, выберите тип **Docker Compose**, подключите репозиторий и укажите `docker-compose.dokploy.yml` как Compose-файл.
2. В разделе **Environment** задайте переменные. Пример ниже нужно заполнить собственными значениями:

   ```dotenv
   IMAGE_REPOSITORY=ghcr.io/s0loparty/vibeup-api
   IMAGE_TAG=latest

   APP_NAME=VibeAPI
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=https://api.example.com
   APP_KEY=base64:ЗАМЕНИТЕ_НА_КЛЮЧ

   DB_DATABASE=vibe
   DB_USERNAME=vibe
   DB_PASSWORD=ЗАМЕНИТЕ_НА_НАДЁЖНЫЙ_ПАРОЛЬ
   REDIS_CLIENT=phpredis
   REDIS_PASSWORD=null
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=database
   CACHE_STORE=database

   BROADCAST_CONNECTION=reverb
   REVERB_APP_ID=ЗАМЕНИТЕ
   REVERB_APP_KEY=ЗАМЕНИТЕ
   REVERB_APP_SECRET=ЗАМЕНИТЕ
   REVERB_HOST=ws.example.com
   REVERB_PORT=443
   REVERB_SCHEME=https

   FILESYSTEM_DISK=minio
   MEDIA_DISK=minio
   AWS_ACCESS_KEY_ID=ЗАМЕНИТЕ
   AWS_SECRET_ACCESS_KEY=ЗАМЕНИТЕ
   AWS_DEFAULT_REGION=us-east-1
   AWS_BUCKET=private-bucket
   AWS_PUBLIC_BUCKET=public-bucket
   AWS_ENDPOINT=https://storage.example.com
   AWS_URL=https://storage.example.com/private-bucket
   AWS_PUBLIC_URL=https://storage.example.com/public-bucket
   AWS_USE_PATH_STYLE_ENDPOINT=true
   ```

   `APP_KEY` должен быть корректным Laravel-ключом; его можно получить локально командой `./vendor/bin/sail artisan key:generate --show --no-interaction`. Сохраните его и не меняйте между развёртываниями. Для MinIO задайте уникальные, достаточно сложные `AWS_ACCESS_KEY_ID` и `AWS_SECRET_ACCESS_KEY`; эти же значения используются как учётные данные администратора MinIO. Имена `AWS_BUCKET` и `AWS_PUBLIC_BUCKET` должны быть разными. `AWS_ENDPOINT` должен указывать на публичный HTTPS-домен MinIO без имени бакета и быть доступен как контейнерам, так и клиентам: он используется для подписи временных ссылок на приватные файлы. Проверяйте настройки почты и других интеграций отдельно.

   Compose передаёт переменные из `.env` Dokploy в `app`, `queue` и `reverb`, а также принудительно задаёт для них PostgreSQL (`postgres:5432`), Redis (`redis:6379`) и очередь Redis. Встроенный Redis не настроен на пароль, поэтому для него нужен `REDIS_PASSWORD=null` без `REDIS_URL` с другими параметрами подключения.

3. В разделе **Domains** назначьте отдельные домены сервисам `webserver` (внутренний порт `80`), `reverb` (`8080`) и `minio` (`9000`, S3 API). Настройте DNS и TLS; `REVERB_HOST` должен быть доступен приложению для отправки событий, а WebSocket-домен — клиентам. Домен MinIO должен совпадать с `AWS_ENDPOINT`; консоль MinIO работает на внутреннем порту `9001` и при необходимости настраивается отдельно. Подробнее: [домены Compose в Dokploy](https://docs.dokploy.com/docs/core/docker-compose/domains).
4. Запустите развёртывание. В терминале контейнера `app` выполните миграции:

   ```bash
   php artisan migrate --force --no-interaction
   ```

   Миграции не выполняются автоматически: применяйте их при первом запуске и после обновлений, которые их содержат. Проверьте логи `app`, `queue` и `reverb` в Dokploy.

Compose запускает PostgreSQL 17, Redis, MinIO, приложение, Nginx, Reverb и обработчик очереди. Сервис `minio-init` дожидается готовности MinIO и запускает `minio:setup-buckets` перед стартом приложения: он создаёт отсутствующие бакеты и настраивает публичное чтение для `AWS_PUBLIC_BUCKET`. При одинаковых именах бакетов инициализация завершается ошибкой. Логи инициализации доступны в Dokploy. Horizon отключён; очередь обрабатывается контейнером `queue`.

PostgreSQL, Redis, MinIO и `storage` используют именованные тома. Настройте резервное копирование и сохраняйте учётные данные PostgreSQL при повторном развёртывании. Локальный Sail использует PostgreSQL 18 и собственный том: перенос данных между окружениями и версиями PostgreSQL требует отдельной миграции, простая подмена тома не подходит.

При обновлении дождитесь завершения публикации в GitHub Actions, выберите нужный `IMAGE_TAG` и повторно разверните Compose в Dokploy. Настройка Compose загружает готовые образы; автоматический вызов развёртывания после публикации в этом репозитории не настроен. Существующий workflow `deploy.yml` отдельно развёртывает ветку `devstage` на прежний VPS.

## Octane

Настройки `OCTANE_SERVER` и `OCTANE_HTTPS` из старого `.env` в текущей конфигурации не используются: приложение работает через PHP-FPM и Nginx. Для перехода на FrankenPHP потребуется отдельное внедрение Laravel Octane.
