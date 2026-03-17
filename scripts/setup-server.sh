#!/bin/bash

# =============================================================
# Vibe API - Server Setup Script
# =============================================================
# Этот скрипт выполняет первоначальную настройку VPS сервера
# Запускать от root пользователя
# =============================================================

set -e

echo "🚀 Starting Vibe API server setup..."

# -------------------------------------------------------------
# 1. Update system packages
# -------------------------------------------------------------
echo "📦 Updating system packages..."
apt-get update
apt-get upgrade -y
apt-get install -y curl git ufw fail2ban

# -------------------------------------------------------------
# 2. Install Docker
# -------------------------------------------------------------
echo "🐳 Installing Docker..."
curl -fsSL https://get.docker.com -o get-docker.sh
sh get-docker.sh
rm get-docker.sh

# Add user to docker group (if not root)
if [ -n "$SUDO_USER" ]; then
    usermod -aG docker $SUDO_USER
fi

# -------------------------------------------------------------
# 3. Install Docker Compose Plugin
# -------------------------------------------------------------
echo "📦 Installing Docker Compose..."
apt-get install -y docker-compose-plugin

# -------------------------------------------------------------
# 4. Configure Firewall (UFW)
# -------------------------------------------------------------
echo "🔥 Configuring firewall..."
ufw default deny incoming
ufw default allow outgoing
ufw allow ssh
ufw allow http
ufw allow https
ufw --force enable

# -------------------------------------------------------------
# 5. Create Application Directory
# -------------------------------------------------------------
echo "📁 Creating application directory..."
mkdir -p /opt/vibe-api
cd /opt/vibe-api

# -------------------------------------------------------------
# 6. Generate Secure Keys
# -------------------------------------------------------------
echo "🔑 Generating secure keys..."

# Generate APP_KEY if not provided
if [ -z "$APP_KEY" ]; then
    APP_KEY=$(openssl rand -base64 32)
fi

# Generate random passwords
DB_PASSWORD=${DB_PASSWORD:-$(openssl rand -base64 32)}
REDIS_PASSWORD=${REDIS_PASSWORD:-$(openssl rand -base64 32)}
MINIO_ACCESS_KEY=${MINIO_ACCESS_KEY:-$(openssl rand -hex 16)}
MINIO_SECRET_KEY=${MINIO_SECRET_KEY:-$(openssl rand -base64 32)}

# -------------------------------------------------------------
# 7. Create Environment File
# -------------------------------------------------------------
echo "📝 Creating environment file..."

cat > /opt/vibe-api/.env << EOF
APP_KEY=${APP_KEY}
APP_ENV=${APP_ENV:-production}
APP_DEBUG=false
APP_URL=${APP_URL:-https://vibe.playerinlife.ru}

DB_CONNECTION=pgsql
DB_HOST=pgsql
DB_PORT=5432
DB_DATABASE=vibe_api
DB_USERNAME=vibe_api
DB_PASSWORD=${DB_PASSWORD}

REDIS_PASSWORD=${REDIS_PASSWORD}

MINIO_ACCESS_KEY=${MINIO_ACCESS_KEY}
MINIO_SECRET_KEY=${MINIO_SECRET_KEY}
MINIO_BUCKET=vibe-uploads

FORWARD_DB_PORT=5432
FORWARD_REDIS_PORT=6379
FORWARD_MINIO_PORT=9000
FORWARD_MINIO_CONSOLE_PORT=8900
FORWARD_MAILPIT_PORT=1025
FORWARD_MAILPIT_DASHBOARD_PORT=8025
EOF

chmod 600 /opt/vibe-api/.env

# -------------------------------------------------------------
# 8. Copy Docker Compose and Caddyfile
# -------------------------------------------------------------
echo "📋 Copying configuration files..."
echo "⚠️  Please copy docker-compose.prod.yml and Caddyfile to /opt/vibe-api/"
echo "    Or run this script with files already in place."

# -------------------------------------------------------------
# 9. Configure SSH (Security Hardening)
# -------------------------------------------------------------
echo "🔒 Hardening SSH configuration..."
cat >> /etc/ssh/sshd_config << 'EOF'

# Security hardening
PermitRootLogin prohibit-password
PasswordAuthentication no
PubkeyAuthentication yes
X11Forwarding no
MaxAuthTries 3
ClientAliveInterval 300
ClientAliveCountMax 2
EOF

systemctl restart sshd

# -------------------------------------------------------------
# 10. Setup automatic updates
# -------------------------------------------------------------
echo "🔄 Setting up automatic security updates..."
apt-get install -y unattended-upgrades
dpkg-reconfigure -plow unattended-upgrades

# -------------------------------------------------------------
# 11. Display Summary
# -------------------------------------------------------------
echo ""
echo "============================================================"
echo "✅ Server setup completed!"
echo "============================================================"
echo ""
echo "📁 Application directory: /opt/vibe-api"
echo "🔑 APP_KEY: $APP_KEY"
echo ""
echo "📊 Database:"
echo "   Host: localhost (from host) / pgsql (from containers)"
echo "   Database: vibe_api"
echo "   Username: vibe_api"
echo "   Password: $DB_PASSWORD"
echo ""
echo "💾 Redis:"
echo "   Password: $REDIS_PASSWORD"
echo ""
echo "🗄️  MinIO:"
echo "   Access Key: $MINIO_ACCESS_KEY"
echo "   Secret Key: $MINIO_SECRET_KEY"
echo "   Bucket: vibe-uploads"
echo ""
echo "🌐 URLs (after deployment):"
echo "   App: ${APP_URL:-https://vibe.playerinlife.ru}"
echo "   MinIO Console: http://SERVER_IP:8900"
echo "   Mailpit: http://SERVER_IP:8025"
echo ""
echo "⚠️  IMPORTANT: Save these credentials securely!"
echo "============================================================"
echo ""
echo "Next steps:"
echo "1. Copy docker-compose.prod.yml and Caddyfile to /opt/vibe-api/"
echo "2. Configure GitHub Actions secrets"
echo "3. Push to main branch to trigger deployment"
echo ""
