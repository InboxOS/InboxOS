#!/bin/bash

# Validation script for mail server

set -e

echo "Validating mail server setup..."

# Check required files
required_files=(
    "docker-compose.yml"
    ".env"
    "symfony-dashboard/Dockerfile"
    "nginx/Dockerfile"
    "nginx/nginx.conf"
    "symfony-dashboard/composer.json"
)

for file in "${required_files[@]}"; do
    if [ ! -f "$file" ]; then
        echo "❌ Missing required file: $file"
        exit 1
    fi
    echo "✅ $file"
done

# Check environment variables
required_vars=(
    "DOMAIN"
    "ADMIN_EMAIL"
    "MYSQL_PASSWORD"
    "APP_SECRET"
)

source .env 2>/dev/null || true

for var in "${required_vars[@]}"; do
    if [ -z "${!var}" ]; then
        echo "❌ Missing environment variable: $var"
        exit 1
    fi
    echo "✅ $var"
done

# Validate domain format
if [[ ! "$DOMAIN" =~ ^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
    echo "❌ Invalid domain format: $DOMAIN"
    exit 1
fi
echo "✅ Domain format: $DOMAIN"

# Validate email format
if [[ ! "$ADMIN_EMAIL" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
    echo "❌ Invalid email format: $ADMIN_EMAIL"
    exit 1
fi
echo "✅ Email format: $ADMIN_EMAIL"

# Check port availability
ports=(80 443 25 587 465 993 995)
for port in "${ports[@]}"; do
    if lsof -i :$port >/dev/null 2>&1; then
        echo "⚠️  Port $port is already in use"
    else
        echo "✅ Port $port available"
    fi
done

# Check Docker/Podman
if command -v podman >/dev/null 2>&1; then
    echo "✅ Podman found: $(podman --version)"
elif command -v docker >/dev/null 2>&1; then
    echo "✅ Docker found: $(docker --version)"
else
    echo "❌ Neither Podman nor Docker found"
    exit 1
fi

# Check Docker Compose/Podman Compose
if command -v podman-compose >/dev/null 2>&1; then
    echo "✅ Podman Compose found"
elif command -v docker-compose >/dev/null 2>&1; then
    echo "✅ Docker Compose found"
else
    echo "❌ Neither Podman Compose nor Docker Compose found"
    exit 1
fi

# Check disk space
disk_space=$(df -h / | awk 'NR==2 {print $4}')
echo "✅ Disk space available: $disk_space"

# Check memory
total_mem=$(free -h | awk '/^Mem:/ {print $2}')
echo "✅ Total memory: $total_mem"

echo ""
echo "========================================="
echo "Validation successful!"
echo "System is ready for deployment."
echo "Run './deploy.sh' to start the mail server."
echo "========================================="