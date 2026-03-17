# 🎯 Vibe API - DevOps Setup Summary

## ✅ Что было создано

### Файлы для деплоя

| Файл | Назначение |
|------|------------|
| `Dockerfile` | Multi-stage сборка с FrankenPHP (PHP 8.5 + Caddy) |
| `docker-compose.prod.yml` | Production стек (PostgreSQL, Redis, MinIO, Mailpit) |
| `docker-compose.staging.yml` | Staging стек (аналогично production) |
| `Caddyfile` | Конфигурация FrankenPHP для production (SSL, домен) |
| `Caddyfile.staging` | Конфигурация для staging окружения |
| `.dockerignore` | Исключения для Docker build |
| `.github/workflows/deploy.yml` | GitHub Actions workflow (build + test + deploy) |
| `scripts/setup-server.sh` | Скрипт первоначальной настройки VPS |
| `scripts/deploy.sh` | Скрипт ручного деплоя (альтернатива GitHub Actions) |
| `DEPLOYMENT.md` | Полная документация по деплою |
| `CI_CD_QUICKSTART.md` | Быстрый старт за 10 минут |

---

## 🏗️ Архитектура решения

### CI/CD Pipeline

```
┌─────────────────────────────────────────────────────────────┐
│                    GitHub Actions                            │
│                                                              │
│  git push ──▶ ┌──────────────┐                              │
│               │   Run Tests  │                              │
│               └──────┬───────┘                              │
│                      ▼                                       │
│               ┌──────────────┐                              │
│               │ Build Docker │                              │
│               │   (multi-    │                              │
│               │    stage)    │                              │
│               └──────┬───────┘                              │
│                      ▼                                       │
│               ┌──────────────┐                              │
│               │ Push to GHCR │                              │
│               │  (Container  │                              │
│               │   Registry)  │                              │
│               └──────┬───────┘                              │
│                      │                                       │
│         ┌────────────┴────────────┐                         │
│         ▼                         ▼                         │
│  ┌──────────────┐          ┌──────────────┐                │
│  │   Deploy to  │          │   Deploy to  │                │
│  │  Production  │          │   Staging    │                │
│  │   (main)     │          │  (develop)   │                │
│  └──────┬───────┘          └──────┬───────┘                │
└─────────┼─────────────────────────┼─────────────────────────┘
          │                         │
          ▼                         ▼
   ┌─────────────┐           ┌─────────────┐
   │ VPS Prod    │           │ VPS Staging │
   │ 80, 443     │           │ 80, 443     │
   └─────────────┘           └─────────────┘
```

### Docker Compose Stack (на VPS)

```
┌─────────────────────────────────────────────────────┐
│                 Docker Compose                       │
│                                                      │
│  ┌──────────────┐  ┌──────────────┐  ┌───────────┐ │
│  │  FrankenPHP  │  │  PostgreSQL  │  │   Redis   │ │
│  │  (Caddy +    │  │   5432       │  │   6379    │ │
│  │   SSL/TLS)   │  │              │  │           │ │
│  │  80/443      │  └──────────────┘  └───────────┘ │
│  └──────────────┘                                   │
│  ┌──────────────┐  ┌──────────────┐                │
│  │    MinIO     │  │   Mailpit    │                │
│  │  9000/8900   │  │   1025/8025  │                │
│  └──────────────┘  └──────────────┘                │
│                                                      │
│  Volumes: pgsql_data, redis_data, minio_data,       │
│           app_storage                                │
└─────────────────────────────────────────────────────┘
```

---

## 🚀 Как использовать

### Вариант 1: GitHub Actions (рекомендуется)

1. **Настрой GitHub Secrets** (см. `CI_CD_QUICKSTART.md`)
2. **Подготовь серверы** (запусти `scripts/setup-server.sh`)
3. **Скопируй файлы на серверы**:
   ```bash
   # Production
   scp docker-compose.prod.yml Caddyfile root@vibe.playerinlife.ru:/opt/vibe-api/
   
   # Staging
   scp docker-compose.staging.yml Caddyfile.staging root@staging.vibe.playerinlife.ru:/opt/vibe-api/
   ```
4. **Запуш изменения**:
   ```bash
   git push origin main    # production
   git push origin develop # staging
   ```

### Вариант 2: Ручной деплой

```bash
# Сгенерируй токен GitHub для доступа к GHCR
export GITHUB_TOKEN=ghp_xxx
export GITHUB_USERNAME=your-username

# Production
ENVIRONMENT=production IMAGE_TAG=latest SERVER_HOST=vibe.playerinlife.ru ./scripts/deploy.sh

# Staging
ENVIRONMENT=staging IMAGE_TAG=staging SERVER_HOST=staging.vibe.playerinlife.ru ./scripts/deploy.sh
```

---

## 🔐 Безопасность

### Что настроено

✅ **SSH ключи** для доступа без пароля  
✅ **Firewall (UFW)** - только порты 22, 80, 443  
✅ **SSL/TLS** автоматически от Let's Encrypt  
✅ **Изоляция сервисов** через Docker network  
✅ **Пароли** для БД, Redis, MinIO  
✅ **Non-root user** внутри контейнера  
✅ **Health checks** для всех сервисов  

### Рекомендации

1. Смени root логин на SSH ключи
2. Настрой fail2ban
3. Включи automatic security updates
4. Регулярно обновляй Docker образы
5. Делай backup баз данных

---

## 📊 Мониторинг

### Laravel Horizon

```bash
# Production
https://vibe.playerinlife.ru/horizon

# Staging
https://staging.vibe.playerinlife.ru/horizon
```

### Docker логи

```bash
# Production
docker compose -f docker-compose.prod.yml logs -f

# Staging
docker compose -f docker-compose.staging.yml logs -f
```

### GitHub Actions

```
https://github.com/YOUR_USERNAME/vibe-api/actions
```

---

## 🎯 Ветки и окружения

| Ветка | Окружение | URL | Docker tag |
|-------|-----------|-----|------------|
| `main` | Production | https://vibe.playerinlife.ru | `latest` |
| `develop` | Staging | https://staging.vibe.playerinlife.ru | `staging` |

---

## 📝 Полезные команды

### Локальная разработка

```bash
# Запуск через Sail
./vendor/bin/sail up -d

# Запуск без Sail (Docker Compose)
docker compose -f compose.yaml up -d

# Тесты
composer test

# Build assets
npm run build
```

### На сервере

```bash
# Статус сервисов
docker compose -f docker-compose.prod.yml ps

# Логи
docker compose -f docker-compose.prod.yml logs -f app

# Вход в контейнер
docker compose -f docker-compose.prod.yml exec app bash

# Миграции
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force

# Очистка кэша
docker compose -f docker-compose.prod.yml run --rm app php artisan optimize:clear

# Tinker
docker compose -f docker-compose.prod.yml run --rm app php artisan tinker

# Перезапуск
docker compose -f docker-compose.prod.yml restart

# Остановка
docker compose -f docker-compose.prod.yml down
```

---

## 🔧 Troubleshooting

### Деплой не работает

1. Проверь логи GitHub Actions
2. Убедись что все секреты настроены верно
3. Проверь SSH подключение: `ssh -i ~/.ssh/vibe-api-production root@vibe.playerinlife.ru`

### SSL не работает

Подожди 1-2 минуты после первого запуска. FrankenPHP автоматически получает сертификат.

```bash
docker compose -f docker-compose.prod.yml logs app | grep -i "certificate"
```

### Миграции не проходят

```bash
# Проверь подключение к БД
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate:status

# Запусти заново
docker compose -f docker-compose.prod.yml run --rm app php artisan migrate --force
```

### Очистка старых образов

```bash
# На сервере
docker image prune -af --filter "until=24h"
```

---

## 📚 Документация

- `DEPLOYMENT.md` - полное руководство по деплою
- `CI_CD_QUICKSTART.md` - быстрый старт
- `AGENTS.md` - guidelines для Laravel Boost
- `README.md` - общая информация о проекте

---

## ✅ Checklist перед первым деплоем

- [ ] GitHub аккаунт подключён к Container Registry
- [ ] SSH ключи сгенерированы
- [ ] Секреты добавлены в GitHub
- [ ] VPS серверы настроены (Docker установлен)
- [ ] DNS записи созданы
- [ ] Файлы docker-compose и Caddyfile скопированы на серверы
- [ ] Тесты проходят локально
- [ ] `.env` файлы проверены

---

**Готово! 🎉**

Теперь у тебя есть полноценный CI/CD pipeline с автоматическим деплоем на production и staging окружения.
