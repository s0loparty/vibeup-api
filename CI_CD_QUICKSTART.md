# 🚀 Vibe API - CI/CD Quick Start

## Быстрый старт за 10 минут

### Шаг 1: Создай GitHub Secrets

#### 1.1. Сгенерируй SSH ключи

```bash
# Для production
ssh-keygen -t ed25519 -C "github-actions-production" -f ~/.ssh/vibe-api-production

# Для staging
ssh-keygen -t ed25519 -C "github-actions-staging" -f ~/.ssh/vibe-api-staging
```

#### 1.2. Скопируй публичные ключи на серверы

```bash
# Production
ssh-copy-id -i ~/.ssh/vibe-api-production.pub root@<PRODUCTION_IP>

# Staging
ssh-copy-id -i ~/.ssh/vibe-api-staging.pub root@<STAGING_IP>
```

#### 1.3. Добавь секреты в GitHub

Зайди в: `https://github.com/YOUR_USERNAME/vibe-api/settings/secrets/actions`

**Production secrets:**

```bash
# Пройди в Settings → Secrets and variables → Actions → New repository secret

PRODUCTION_HOST=<IP production сервера>
PRODUCTION_SSH_PRIVATE_KEY=<содержимое ~/.ssh/vibe-api-production>
PRODUCTION_APP_KEY=<сгенерируй: php -r "echo 'base64:' . base64_encode(random_bytes(32));">
PRODUCTION_DB_DATABASE=vibe_api
PRODUCTION_DB_USERNAME=vibe_api
PRODUCTION_DB_PASSWORD=<сложный пароль>
PRODUCTION_REDIS_PASSWORD=<сложный пароль>
PRODUCTION_MINIO_ACCESS_KEY=minioadmin$(date +%s)
PRODUCTION_MINIO_SECRET_KEY=<сложный пароль>
PRODUCTION_MINIO_BUCKET=vibe-uploads
```

**Staging secrets:**

```bash
STAGING_HOST=<IP staging сервера>
STAGING_SSH_PRIVATE_KEY=<содержимое ~/.ssh/vibe-api-staging>
STAGING_APP_KEY=<другой ключ>
STAGING_DB_DATABASE=vibe_api_staging
STAGING_DB_USERNAME=vibe_api_staging
STAGING_DB_PASSWORD=<сложный пароль>
STAGING_REDIS_PASSWORD=<сложный пароль>
STAGING_MINIO_ACCESS_KEY=minioadmin$(date +%s)
STAGING_MINIO_SECRET_KEY=<сложный пароль>
STAGING_MINIO_BUCKET=vibe-uploads-staging
```

---

### Шаг 2: Настрой серверы

#### 2.1. Production сервер

```bash
# Подключись к серверу
ssh root@<PRODUCTION_IP>

# Обнови систему
apt-get update && apt-get upgrade -y

# Установи Docker
curl -fsSL https://get.docker.com | sh

# Установи Docker Compose
apt-get install -y docker-compose-plugin

# Создай директорию
mkdir -p /opt/vibe-api
cd /opt/vibe-api

# Настрой firewall
ufw allow 22 && ufw allow 80 && ufw allow 443 && ufw --force enable
```

#### 2.2. Staging сервер

```bash
# Аналогично production
ssh root@<STAGING_IP>
# ... выполни те же команды
```

---

### Шаг 3: Скопируй файлы на серверы

```bash
# Production
scp docker-compose.prod.yml root@<PRODUCTION_IP>:/opt/vibe-api/
scp Caddyfile root@<PRODUCTION_IP>:/opt/vibe-api/

# Staging
scp docker-compose.staging.yml root@<STAGING_IP>:/opt/vibe-api/
scp Caddyfile.staging root@<STAGING_IP>:/opt/vibe-api/
```

---

### Шаг 4: Настрой DNS

У твоего доменного регистратора создай A записи:

```
vibe.playerinlife.ru           A    <PRODUCTION_IP>
staging.vibe.playerinlife.ru   A    <STAGING_IP>
```

---

### Шаг 5: Запуш и задеплой

```bash
# Добавь все файлы
git add .
git commit -m "Setup CI/CD pipeline"

# Запуш в main (production)
git push origin main

# Запуш в develop (staging)
git checkout -b develop
git push origin develop
```

---

### Шаг 6: Проверь деплой

#### GitHub Actions

Зайди в: `https://github.com/YOUR_USERNAME/vibe-api/actions`

Должны запуститься workflow:
- ✅ Tests
- ✅ Build and Push
- ✅ Deploy to Production / Staging

#### Проверка URL

```bash
# Production
curl https://vibe.playerinlife.ru/api/v1/user

# Staging
curl https://staging.vibe.playerinlife.ru/api/v1/user
```

---

## 🔧 Полезные команды

### Проверка статусов на сервере

```bash
# Production
ssh root@vibe.playerinlife.ru
cd /opt/vibe-api
docker compose -f docker-compose.prod.yml ps
docker compose -f docker-compose.prod.yml logs -f app

# Staging
ssh root@staging.vibe.playerinlife.ru
cd /opt/vibe-api
docker compose -f docker-compose.staging.yml ps
docker compose -f docker-compose.staging.yml logs -f app
```

### Логи в GitHub Actions

```bash
# Посмотреть логи деплоя
https://github.com/YOUR_USERNAME/vibe-api/actions
```

---

## 🎯 Что происходит при деплое

```
git push → GitHub Actions → Tests → Build Docker → Push to GHCR → Deploy to VPS
                                                                        ↓
                                                            docker compose up -d
                                                            php artisan migrate
                                                            php artisan optimize
```

---

## ❓ Troubleshooting

### Деплой не запускается

1. Проверь что ветка `main` или `develop`
2. Проверь логи в GitHub Actions
3. Убедись что все секреты настроены

### Ошибка SSH

```bash
# Проверь что ключ работает
ssh -i ~/.ssh/vibe-api-production root@<PRODUCTION_IP>

# Если не работает, пересоздай ключ
ssh-keygen -t ed25519 -f ~/.ssh/vibe-api-production
ssh-copy-id -i ~/.ssh/vibe-api-production.pub root@<PRODUCTION_IP>
```

### SSL не работает

Подожди 1-2 минуты. FrankenPHP автоматически получает сертификат Let's Encrypt при первом запуске.

```bash
# Проверь логи
docker compose -f docker-compose.prod.yml logs app | grep -i "certificate"
```

---

## 📊 Мониторинг после деплоя

### Horizon (очереди)

```
https://vibe.playerinlife.ru/horizon
https://staging.vibe.playerinlife.ru/horizon
```

### MinIO Console (файлы)

```
http://<PRODUCTION_IP>:8900
http://<STAGING_IP>:8900

Login: PRODUCTION_MINIO_ACCESS_KEY / PRODUCTION_MINIO_SECRET_KEY
```

### Mailpit (письма)

```
http://<PRODUCTION_IP>:8025
http://<STAGING_IP>:8025
```

---

## ✅ Checklist

- [ ] SSH ключи сгенерированы и скопированы на серверы
- [ ] GitHub Secrets добавлены
- [ ] Docker установлен на серверы
- [ ] Файлы docker-compose и Caddyfile скопированы
- [ ] DNS настроен
- [ ] Деплой прошёл успешно
- [ ] HTTPS работает
- [ ] API отвечает

---

**Готово! 🎉**

Теперь каждый push в `main` будет деплоить на production, а в `develop` — на staging.
