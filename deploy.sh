#!/bin/bash

# Deployment script for mail server

set -e

echo "🚀 Starting Mail Server Deployment..."

# ------------------------------------------------------------------
# 1. Automatic Socket Detection
# ------------------------------------------------------------------
if [ -w /var/run/docker.sock ]; then
    SOCKET_PATH="/var/run/docker.sock"
elif [ -w /run/podman/podman.sock ]; then
    SOCKET_PATH="/run/podman/podman.sock"
elif [ -n "$XDG_RUNTIME_DIR" ] && [ -w "$XDG_RUNTIME_DIR/podman/podman.sock" ]; then
    SOCKET_PATH="$XDG_RUNTIME_DIR/podman/podman.sock"
else
    echo "🔍 probing podman..."
    SOCKET_PATH=$(podman info --format '{{.Host.RemoteSocket.Path}}')
fi

echo "✅ Detected Socket: $SOCKET_PATH"
export DOCKER_SOCKET=$SOCKET_PATH

# Check if .env exists
if [ ! -f ".env" ]; then
    echo "Creating .env file from template..."
    if [ -f ".env.example" ]; then
        cp .env.example .env
    else
        # Fallback generator if example is missing
        echo "📝 .env.example not found. Generating default .env..."
        cat <<EOF > .env
DOMAIN=localhost
ADMIN_EMAIL=admin@example.com
MYSQL_ROOT_PASSWORD=root
MYSQL_PASSWORD=password
MYSQL_DATABASE=mail_dashboard
APP_SECRET=$(openssl rand -hex 16)
REDIS_PASSWORD=redispass
DEFAULT_QUOTA_MB=1024
MAX_MESSAGE_SIZE=26214400
TZ=UTC
POSTMASTER_ADDRESS=postmaster@localhost
REPORT_RECIPIENT=admin@example.com
SERVER_IP=127.0.0.1
LETSENCRYPT_EMAIL=admin@example.com
LETSENCRYPT_HOST=localhost
HORDE_ADMIN_PASSWORD=hordepass
MAIL_SERVER_PATH=./data/mail
CERTIFICATE_PATH=./certificates
DKIM_SELECTOR=mail
SPF_RECORD="v=spf1 mx ~all"
DMARC_RECORD="v=DMARC1; p=none; rua=mailto:dmarc@localhost"
EOF
    fi
else
    echo "ℹ️  Using existing .env configuration."
fi

# Ensure DOCKER_SOCKET is in .env or exported
if ! grep -q "DOCKER_SOCKET" .env; then
     echo "DOCKER_SOCKET=$SOCKET_PATH" >> .env
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

# Generate Let's Encrypt certificates
echo "Generating Let's Encrypt certificates..."
$COMPOSE_CMD restart certbot
$COMPOSE_CMD exec nginx nginx -s reload

# Create admin user
echo "Creating admin user..."
$COMPOSE_CMD exec symfony-dashboard php bin/console app:create-admin \
    --email=${ADMIN_EMAIL} \
    --password=ChangeMe123

# Configure startup persistence
echo "Configuring automatic startup on reboot..."
if [ "$(id -u)" -eq 0 ]; then
    # Rootful mode
    systemctl enable --now podman-restart
    echo "✅ Enabled podman-restart service (Rootful)."
else
    # Rootless mode
    if command -v loginctl >/dev/null; then
        loginctl enable-linger $(whoami)
        echo "✅ Enabled lingering for user $(whoami)."
    fi
    systemctl --user enable --now podman-restart
    echo "✅ Enabled podman-restart service (Rootless)."
fi

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