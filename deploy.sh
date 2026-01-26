#!/bin/bash

# Deployment script for mail server

set -e

echo "🚀 Starting Mail Server Deployment..."

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "Creating .env file from template..."
    cp .env.example .env
    echo "Please edit .env file with your configuration"
    exit 1
fi

# Load environment variables
source .env

# Create necessary directories
echo "Creating directories..."
mkdir -p data/mysql
mkdir -p data/mailserver/{mail-data,mail-state,mail-logs,config}
mkdir -p data/nginx/{logs,www}
mkdir -p data/redis
mkdir -p data/dashboard/{uploads,backups}
mkdir -p data/horde/{config,data}
mkdir -p certificates
mkdir -p backup-scripts
mkdir -p backups

# Set permissions
echo "Setting permissions..."
chmod -R 755 data/
chmod +x backup-scripts/*.sh
chmod +x deploy.sh

# Generate SSL certificates if needed
if [ ! -f "certificates/privkey.pem" ]; then
    echo "Generating self-signed certificates for initial setup..."
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout certificates/privkey.pem \
        -out certificates/fullchain.pem \
        -subj "/C=US/ST=State/L=City/O=Organization/CN=${DOMAIN}"
fi

# Build and start containers
echo "Building and starting containers..."

# Check if podman-compose is available
if command -v podman-compose &> /dev/null; then
    COMPOSE_CMD="podman-compose"
elif command -v docker-compose &> /dev/null; then
    COMPOSE_CMD="docker-compose"
else
    echo "Error: Neither podman-compose nor docker-compose found!"
    exit 1
fi

# Build images
$COMPOSE_CMD build

# Start services
$COMPOSE_CMD up -d

# Wait for services to be ready
echo "Waiting for services to start..."
sleep 30

# Initialize Symfony database
echo "Initializing Symfony database..."
$COMPOSE_CMD exec symfony-dashboard php bin/console doctrine:database:create --if-not-exists
$COMPOSE_CMD exec symfony-dashboard php bin/console doctrine:schema:update --force
$COMPOSE_CMD exec symfony-dashboard php bin/console doctrine:fixtures:load -n

# Generate Let's Encrypt certificates
echo "Generating Let's Encrypt certificates..."
$COMPOSE_CMD restart certbot
$COMPOSE_CMD exec nginx nginx -s reload

# Create admin user
echo "Creating admin user..."
$COMPOSE_CMD exec symfony-dashboard php bin/console app:create-admin \
    --email=${ADMIN_EMAIL} \
    --password=ChangeMe123

echo "✅ Deployment completed!"
echo ""
echo "Access your mail server at:"
echo "  Dashboard: https://${DOMAIN}"
echo "  Webmail: https://webmail.${DOMAIN}"
echo ""
echo "Default admin credentials:"
echo "  Email: ${ADMIN_EMAIL}"
echo "  Password: ChangeMe123"
echo ""
echo "Please change the default password immediately!"