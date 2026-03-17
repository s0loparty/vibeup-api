#!/bin/bash

# =============================================================
# Vibe API - Manual Deploy Script
# =============================================================
# Для ручного деплоя на сервер (альтернатива GitHub Actions)
# =============================================================

set -e

# Configuration
SERVER_HOST="${SERVER_HOST:-}"
SERVER_USER="${SERVER_USER:-root}"
IMAGE_TAG="${IMAGE_TAG:-latest}"
ENVIRONMENT="${ENVIRONMENT:-production}"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Vibe API Deployment${NC}"
echo "================================"
echo "Server: $SERVER_HOST"
echo "Environment: $ENVIRONMENT"
echo "Image Tag: $IMAGE_TAG"
echo ""

# Check configuration
if [ -z "$SERVER_HOST" ]; then
    echo -e "${RED}❌ SERVER_HOST is required${NC}"
    echo "Usage: SERVER_HOST=your-server-ip ./deploy.sh"
    exit 1
fi

# Select compose file
if [ "$ENVIRONMENT" = "staging" ]; then
    COMPOSE_FILE="docker-compose.staging.yml"
    IMAGE_NAME="staging"
else
    COMPOSE_FILE="docker-compose.prod.yml"
    IMAGE_NAME="latest"
fi

# Build and push Docker image
echo -e "${YELLOW}📦 Building Docker image...${NC}"
docker build -t ghcr.io/$(basename $(dirname $(pwd)))/vibe-api:$IMAGE_TAG .

# Login to GHCR
echo -e "${YELLOW}🔐 Logging in to GitHub Container Registry...${NC}"
echo $GITHUB_TOKEN | docker login ghcr.io -u $GITHUB_USERNAME --password-stdin

# Push image
echo -e "${YELLOW}📤 Pushing image to registry...${NC}"
docker push ghcr.io/$(basename $(dirname $(pwd)))/vibe-api:$IMAGE_TAG

# Deploy to server
echo -e "${YELLOW}🚀 Deploying to server...${NC}"
ssh $SERVER_USER@$SERVER_HOST << 'ENDSSH'
set -e

cd /opt/vibe-api

# Pull new image
docker pull ghcr.io/'$GITHUB_USERNAME'/vibe-api:'$IMAGE_NAME'

# Run migrations
docker compose -f '$COMPOSE_FILE' run --rm app php artisan migrate --force

# Clear and cache
docker compose -f '$COMPOSE_FILE' run --rm app php artisan optimize:clear
docker compose -f '$COMPOSE_FILE' run --rm app php artisan config:cache
docker compose -f '$COMPOSE_FILE' run --rm app php artisan route:cache

# Restart services
docker compose -f '$COMPOSE_FILE' up -d

# Cleanup
docker image prune -af --filter "until=24h"

echo "✅ Deployment completed!"
ENDSSH

echo -e "${GREEN}✅ Deployment finished successfully!${NC}"
