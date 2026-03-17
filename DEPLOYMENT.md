# 📖 Vibe API - Deployment Guide

Полное руководство по деплою Vibe API на VPS с использованием Docker и GitHub Actions.

---

## 📋 Содержание

1. [Требования](#требования)
2. [Архитектура](#архитектура)
3. [Настройка GitHub Repository](#настройка-github-repository)
4. [Настройка VPS серверов](#настройка-vps-серверов)
5. [GitHub Secrets](#github-secrets)
6. [Первый деплой](#первый-деплой)
7. [Мониторинг](#мониторинг)
8. [Troubleshooting](#troubleshooting)

---

## 🎯 Требования

### Локально (для разработки)
- Docker Desktop / Docker + Docker Compose
- PHP 8.5
- Node.js 22
- Git

### VPS серверы (Production & Staging)
- Ubuntu 22.04+ или Debian 12+
- Минимум 2GB RAM, 2 CPU ядра
- root доступ по SSH
- Домен с DNS записями на сервер

---

## 🏗️ Архитектура

```
┌─────────────────────────────────────────────────────────┐
│                    GitHub Actions                        │
│  ┌──────────┐  ┌──────────┐  ┌──────────────────────┐  │
│  │  Tests   │─▶│  Build   │─▶│ Deploy (SSH to VPS) │  │
│  └──────────┘  └──────────┘  └──────────────────────┘  │
└─────────────────────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────┐
│                    VPS Server                            │
│  ┌──────────────────────────────────────────────────┐   │
│  │              Docker Compose Stack                 │   │
│  │  ┌─────────────┐  ┌──────────┐  ┌────────────┐  │   │
│  │  │  FrankenPHP │  │PostgreSQL│  │   Redis    │  │   │
│  │  │  Port 80/443│  │ Port 5432│  │  Port 6379 │  │   │
│  │  └─────────────┘  └──────────┘  └────────────┘  │   │
│  │  ┌─────────────┐  ┌──────────┐                  │   │
│  │  │    MinIO    │  │  Mailpit │                  │   │
│  │  │Port 9000/8900│ │ Port 8025│                  │   │
│  │  └─────────────┘  └──────────┘                  │   │
│  └──────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────┘
```

---

## ⚙️ Настройка GitHub Repository

### 1. Включи GitHub Container Registry

1. Зайди в GitHub Settings → Packages
2. Убедись, что Container Registry включен
3. Создай токен с правами `write:packages` и `read:packages`

### 2. Создай GitHub Environments

В репозитории зайди в **Settings → Environments** и создай:

#### Environment: `production`
- Name: `production`
- Deployment branches: `main`
- Required reviewers: (опционально)
- Environment variables:
  - `APP_URL`: `https://vibe.playerinlife.ru`

#### Environment: `staging`
- Name: `staging`
- Deployment branches: `develop`
- Environment variables:
  - `APP_URL`: `https://staging.vibe.playerinlife.ru`

---

## 🔐 GitHub Secrets

### Для Production (`Settings → Secrets and variables → Actions → New repository secret`)

| Secret Name | Description | Example |
|-------------|-------------|---------|
| `PRODUCTION_HOST` | IP адрес production сервера | `192.168.1.100` |
| `PRODUCTION_SSH_PRIVATE_KEY` | SSH ключ для доступа к серверу | `-----BEGIN OPENSSH PRIVATE KEY-----...` |
| `PRODUCTION_APP_KEY` | Laravel APP_KEY (32 символа) | `base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx` |
| `PRODUCTION_DB_DATABASE` | Имя БД | `vibe_api` |
| `PRODUCTION_DB_USERNAME` | Пользователь БД | `vibe_api` |
| `PRODUCTION_DB_PASSWORD` | Пароль БД (сложный) | `SuperSecret123!` |
| `PRODUCTION_REDIS_PASSWORD` | Пароль Redis | `RedisPass456!` |
| `PRODUCTION_MINIO_ACCESS_KEY` | MinIO access key | `minioadmin123` |
| `PRODUCTION_MINIO_SECRET_KEY` | MinIO secret key | `MinioSecret789!` |
| `PRODUCTION_MINIO_BUCKET` | MinIO bucket name | `vibe-uploads` |

### Для Staging (аналогично)

| Secret Name | Description |
|-------------|-------------|
| `STAGING_HOST` | IP адрес staging сервера |
| `STAGING_SSH_PRIVATE_KEY` | SSH ключ для staging |
| `STAGING_APP_KEY` | Laravel APP_KEY для staging |
| `STAGING_DB_DATABASE` | Имя БД |
| `STAGING_DB_USERNAME` | Пользователь БД |
| `STAGING_DB_PASSWORD` | Пароль БД |
| `STAGING_REDIS_PASSWORD` | Пароль Redis |
| `STAGING_MINIO_ACCESS_KEY` | MinIO access key |
| `STAGING_MINIO_SECRET_KEY` | MinIO secret key |
| `STAGING_MINIO_BUCKET` | MinIO bucket name |

### Как сгенерировать SSH ключ

```bash
# Генерация ключа
ssh-keygen -t ed25519 -C "github-actions-vibe-api" -f ~/.ssh/vibe-api-github

# Копирование приватного ключа (для GitHub Secrets)
cat ~/.ssh/vibe-api-github | pbcopy

# Копирование публичного ключа (для сервера)
cat ~/.ssh/vibe-api-github.pub
```

---

## 🖥️ Настройка VPS серверов

### 1. Production сервер

```bash
# Подключись к серверу
ssh root@vibe.playerinlife.ru

# Скачай скрипт настройки
curl -o setup-server.sh https://raw.githubusercontent.com/YOUR_USERNAME/vibe-api/main/scripts/setup-server.sh

# Запусти скрипт
chmod +x setup-server.sh
./setup-server.sh
```

**Или вручную:**

```bash
# 1. Обнови систему
apt-get update && apt-get upgrade -y

# 2. Установи Docker
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh

# 3. Установи Docker Compose Plugin
apt-get install -y docker-compose-plugin

# 4. Создай директорию
mkdir -p /opt/vibe-api
cd /opt/vibe-api

# 5. Настрой firewall
ufw allow 22
ufw allow 80
ufw allow 443
ufw --force enable
```

### 2. Staging сервер

Аналогично production, но используй поддомен `staging.vibe.playerinlife.ru`

---

## 🚀 Первый деплой

### Шаг 1: Подготовь файлы на сервере

```bash
# На production сервере
ssh root@vibe.playerinlife.ru

mkdir -p /opt/vibe-api
cd /opt/vibe-api

# Скопируй файлы (локально из проекта)
scp docker-compose.prod.yml root@vibe.playerinlife.ru:/opt/vibe-api/
scp Caddyfile root@vibe.playerinlife.ru:/opt/vibe-api/

# На staging сервере
scp docker-compose.staging.yml root@staging.vibe.playerinlife.ru:/opt/vibe-api/
scp Caddyfile.staging root@staging.vibe.playerinlife.ru:/opt/vibe-api/
```

### Шаг 2: Настрой DNS

Создай A записи у твоего доменного регистратора:

```
vibe.playerinlife.ru    A    <IP production сервера>
staging.vibe.playerinlife.ru    A    <IP staging сервера>
```

### Шаг 3: Запуш изменения

```bash
git add .
git commit -m "Setup CI/CD pipeline"
git push origin main
```

GitHub Actions автоматически:
1. Запустит тесты
2. Соберёт Docker образ
3. Запушит в GHCR
4. Задеплоит на production

Для staging:
```bash
git checkout develop
git push origin develop
```

---

## 📊 Мониторинг

### Laravel Horizon

Horizon автоматически доступен после деплоя:

- **Production**: `https://vibe.playerinlife.ru/horizon`
- **Staging**: `https://staging.vibe.playerinlife.ru/horizon`

Для доступа настрой middleware в `app/Providers/HorizonServiceProvider.php`:

```php
Horizon::auth(function ($request) {
    return app()->environment('local') || 
           $request->user()?->isAdmin();
});
```

### Docker логи

```bash
# Production
docker compose -f docker-compose.prod.yml logs -f app
docker compose -f docker-compose.prod.yml logs -f pgsql

# Staging
docker compose -f docker-compose.staging.yml logs -f app
```

### Проверка статусов

```bash
# Статус контейнеров
docker compose -f docker-compose.prod.yml ps

# Использование ресурсов
docker stats

# Проверка здоровья
docker compose -f docker-compose.prod.yml exec app php artisan horizon:status
```

---

## 🔧 Troubleshooting

### Ошибка: "Cannot connect to the Docker daemon"

```bash
# Перезапусти Docker
systemctl restart docker
```

### Ошибка: "Permission denied"

```bash
# Проверь права
chmod 755 /opt/vibe-api
chown -R root:root /opt/vibe-api
```

### SSL не работает

FrankenPHP автоматически получает SSL сертификаты от Let's Encrypt. Проверь логи:

```bash
docker compose -f docker-compose.prod.yml logs app | grep -i ssl
```

### Миграции не работают

```bash
# Запусти миграции вручную
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
```

### Очистка кэша

```bash
docker compose -f docker-compose.prod.yml run --rm app php artisan optimize:clear
```

### Rollback

```bash
# Откат к предыдущей версии
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate:rollback

# Или к конкретной версии
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate:rollback --step=3
```

---

## 📝 Полезные команды

### Production

```bash
# Перезапуск
docker compose -f docker-compose.prod.yml restart

# Остановка
docker compose -f docker-compose.prod.yml down

# Логи
docker compose -f docker-compose.prod.yml logs -f

# Вход в контейнер
docker compose -f docker-compose.prod.yml exec app bash

# Tinker
docker compose -f docker-compose.prod.yml exec app php artisan tinker
```

### Staging

```bash
# Аналогично, но с docker-compose.staging.yml
docker compose -f docker-compose.staging.yml [command]
```

---

## 🎯 Checklist перед деплоем

- [ ] Все GitHub Secrets настроены
- [ ] SSH ключи добавлены на серверы
- [ ] DNS записи настроены
- [ ] Firewall открыл порты 80, 443, 22
- [ ] APP_KEY сгенерирован и добавлен в secrets
- [ ] Базы данных настроены
- [ ] MinIO bucket создан
- [ ] Тесты проходят локально
- [ ] `.env` файлы проверены

---

## 📞 Support

При проблемах:
1. Проверь GitHub Actions логи
2. Проверь Docker логи на сервере
3. Убедись что все сервисы в `docker compose ps` имеют статус `healthy`
