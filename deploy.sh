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

# Rootless Podman cannot bind privileged ports (<1024).
# If running rootless and port overrides are not set, set safe defaults.
if [ "$(id -u)" -ne 0 ]; then
    echo "ℹ️  Rootless mode detected. Using high-port mappings for privileged services (override in .env if needed)."

    set_default_env() {
        local key="$1"
        local val="$2"
        if ! grep -q "^${key}=" .env; then
            echo "${key}=${val}" >> .env
        fi
        export "${key}=${val}"
    }

    set_default_env HTTP_PORT 8080
    set_default_env HTTPS_PORT 8443
    set_default_env SMTP_PORT 1025
    set_default_env POP3_PORT 1110
    set_default_env IMAP_PORT 1143
    set_default_env SMTPS_PORT 1465
    set_default_env SUBMISSION_PORT 1587
    set_default_env IMAPS_PORT 1993
    set_default_env POP3S_PORT 1995

    # Localhost dev: don't rely on Let's Encrypt certs.
    if [ "${DOMAIN:-}" = "localhost" ]; then
        set_default_env MAILSERVER_SSL_TYPE none
    fi
fi

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
mkdir -p mysql-init
mkdir -p backups

# Set permissions
echo "Setting permissions..."
if compgen -G "backup-scripts/*.sh" > /dev/null; then
    chmod +x backup-scripts/*.sh
fi
chmod +x deploy.sh

# Generate SSL certificates if needed (skip for localhost)
if [ "${DOMAIN:-}" = "localhost" ]; then
    echo "ℹ️  DOMAIN=localhost detected. Skipping certificate generation."
else
    if [ ! -f "certificates/privkey.pem" ]; then
        echo "Generating self-signed certificates for initial setup..."
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout certificates/privkey.pem \
            -out certificates/fullchain.pem \
            -subj "/C=US/ST=State/L=City/O=Organization/CN=${DOMAIN}"
    fi
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

# Compose profiles (docker compose compatible)
# - letsencrypt: runs certbot + TLS nginx templates
# - webmail: runs horde (optional)
COMPOSE_PROFILE_ARGS=""
if [ "${DOMAIN:-}" != "localhost" ]; then
    COMPOSE_PROFILE_ARGS="--profile letsencrypt"
fi
if [ "${ENABLE_WEBMAIL:-0}" = "1" ] || [ "${ENABLE_WEBMAIL:-}" = "true" ]; then
    COMPOSE_PROFILE_ARGS="${COMPOSE_PROFILE_ARGS} --profile webmail"
fi

# Build images
$COMPOSE_CMD $COMPOSE_PROFILE_ARGS build

# Start services
$COMPOSE_CMD $COMPOSE_PROFILE_ARGS up -d

# Wait for services to be ready
echo "Waiting for services to start..."
sleep 30

# Ensure Horde database exists (handles existing MySQL volumes too)
echo "Ensuring Horde database exists..."
$COMPOSE_CMD exec mysql sh -lc "mysql -uroot -p\"$MYSQL_ROOT_PASSWORD\" -e \"CREATE DATABASE IF NOT EXISTS ${HORDE_DB_NAME:-horde} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER IF NOT EXISTS '${HORDE_DB_USER:-horde}'@'%' IDENTIFIED BY '${HORDE_DB_PASSWORD:-ChangeThisHordeDbPassword123}'; GRANT ALL PRIVILEGES ON ${HORDE_DB_NAME:-horde}.* TO '${HORDE_DB_USER:-horde}'@'%'; FLUSH PRIVILEGES;\""

# Initialize Symfony database
echo "Initializing Symfony database..."
$COMPOSE_CMD exec symfony-dashboard php bin/console doctrine:database:create --if-not-exists
$COMPOSE_CMD exec symfony-dashboard php bin/console doctrine:schema:update --force

# Generate Let's Encrypt certificates (never on localhost)
if [ "${DOMAIN:-}" != "localhost" ]; then
    echo "Generating Let's Encrypt certificates..."
    $COMPOSE_CMD $COMPOSE_PROFILE_ARGS restart certbot
    $COMPOSE_CMD exec nginx nginx -s reload
else
    echo "ℹ️  Skipping Let's Encrypt on localhost."
fi

# Create admin user
echo "Creating admin user..."
$COMPOSE_CMD exec symfony-dashboard php bin/console app:create-admin \
    --email="${ADMIN_EMAIL}" \
    --password="${ADMIN_PASSWORD:-ChangeMe123}" \
    --update

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
if [ "${DOMAIN:-}" = "localhost" ]; then
    echo "  Dashboard: http://localhost:${HTTP_PORT:-8080}/login"
else
    echo "  Dashboard: https://${DOMAIN}"
    echo "  Webmail: https://webmail.${DOMAIN}"
fi
echo ""
echo "Default admin credentials:"
echo "  Email: ${ADMIN_EMAIL}"
echo "  Password: ${ADMIN_PASSWORD:-ChangeMe123}"
echo ""
echo "Please change the default password immediately!"