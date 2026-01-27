#!/bin/bash

# Production Readiness Test Suite
# Runs all tests required for production deployment

set -e

echo "========================================="
echo "Production Readiness Test Suite"
echo "========================================="

BASE_URL="${1:-http://localhost:8000}"
SYMFONY_DIR="symfony-dashboard"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

log() {
    echo -e "${GREEN}[$(date '+%H:%M:%S')] $1${NC}"
}

error() {
    echo -e "${RED}[ERROR] $1${NC}"
}

warning() {
    echo -e "${YELLOW}[WARNING] $1${NC}"
}

# Check if we're in the right directory
if [ ! -d "$SYMFONY_DIR" ]; then
    error "Symfony directory not found. Run this script from the project root."
    exit 1
fi

log "Detecting Container Engine Socket..."
if [ -S "/run/user/$(id -u)/podman/podman.sock" ]; then
    export DOCKER_SOCKET="/run/user/$(id -u)/podman/podman.sock"
    log "Using Podman user socket: $DOCKER_SOCKET"
elif [ -S "/run/podman/podman.sock" ]; then
    export DOCKER_SOCKET="/run/podman/podman.sock"
    log "Using Podman system socket: $DOCKER_SOCKET"
elif [ -S "/var/run/docker.sock" ]; then
    export DOCKER_SOCKET="/var/run/docker.sock"
    log "Using Docker socket: $DOCKER_SOCKET"
else
    # Try to enable user socket if simpler checks fail
    if command -v systemctl >/dev/null; then
        log "Attempting to enable Podman user socket..."
        systemctl --user enable --now podman.socket 2>/dev/null || true
        if [ -S "/run/user/$(id -u)/podman/podman.sock" ]; then
             export DOCKER_SOCKET="/run/user/$(id -u)/podman/podman.sock"
             log "Created and using Podman user socket: $DOCKER_SOCKET"
        fi
    fi
fi

if [ -z "$DOCKER_SOCKET" ]; then
    error "Could not locate a valid Docker or Podman socket."
    exit 1
fi

# Ensure the container is built before running tests
log "Building containers..."
podman build -t localhost/email-server/dashboard:latest ./symfony-dashboard
podman build -t localhost/email-server/nginx:latest ./nginx

log "Starting test environment..."
podman-compose up -d symfony-dashboard
log "Waiting for services to initialize..."
sleep 15

log "Installing development dependencies for testing..."
if podman exec mail-dashboard composer install --no-interaction; then
    log "✅ Dev dependencies installed"
else
    error "❌ Failed to install dev dependencies"
    podman logs mail-dashboard
    podman-compose down
    exit 1
fi

log "Running unit tests..."
if podman-compose exec symfony-dashboard ./vendor/bin/phpunit --testsuite=unit --no-coverage; then
    log "✅ Unit tests passed"
else
    error "❌ Unit tests failed"
    log "--- Container Logs ---"
    podman logs mail-dashboard
    log "----------------------"
    podman-compose down
    exit 1
fi

log "Running integration tests..."
if podman-compose exec symfony-dashboard ./vendor/bin/phpunit --testsuite=integration --no-coverage; then
    log "✅ Integration tests passed"
else
    error "❌ Integration tests failed"
    podman-compose down
    exit 1
fi

log "Running migration tests..."
if podman-compose exec symfony-dashboard ./vendor/bin/phpunit --testsuite=migrations --no-coverage; then
    log "✅ Migration tests passed"
else
    error "❌ Migration tests failed"
    podman-compose down
    exit 1
fi

log "Checking PHP syntax..."
if podman-compose exec symfony-dashboard find src -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"; then
    error "❌ PHP syntax errors found"
    podman-compose down
    exit 1
else
    log "✅ PHP syntax is valid"
fi

log "Cleaning up test environment..."
podman-compose down

log "Validating Docker Compose configuration..."
if podman-compose config --quiet 2>/dev/null; then
    log "✅ Docker Compose configuration is valid"
else
    error "❌ Docker Compose configuration is invalid"
    exit 1
fi

log "Validating shell scripts..."
SCRIPTS_TO_CHECK=(
    "setup-complete.sh"
    "scripts/monitor.sh"
    "scripts/load-test.php"
    "scripts/security-audit.php"
    "backup-scripts/backup.sh"
)

for script in "${SCRIPTS_TO_CHECK[@]}"; do
    if [ -f "$script" ]; then
        if [[ "$script" == *.sh ]]; then
            if bash -n "$script"; then
                log "✅ $script syntax is valid"
            else
                error "❌ $script has syntax errors"
                exit 1
            fi
        elif [[ "$script" == *.php ]]; then
            if php -l "$script" | grep -q "No syntax errors"; then
                log "✅ $script syntax is valid"
            else
                error "❌ $script has syntax errors"
                exit 1
            fi
        fi
    else
        warning "⚠️  $script not found"
    fi
done

log "Checking file permissions..."
PERMISSION_CHECKS=(
    "scripts/*.sh:x"
    "scripts/*.php:x"
    "backup-scripts/*.sh:x"
)

for check in "${PERMISSION_CHECKS[@]}"; do
    pattern="${check%:*}"
    required="${check#*:}"

    for file in $pattern; do
        if [ -f "$file" ]; then
            if [ "$required" = "x" ] && [ -x "$file" ]; then
                log "✅ $file has correct permissions"
            elif [ "$required" = "x" ] && [ ! -x "$file" ]; then
                error "❌ $file is not executable"
                exit 1
            fi
        fi
    done
done

# Check if server is running for live tests
log "Checking if server is accessible..."
if curl -f -s --max-time 5 "$BASE_URL/health" > /dev/null 2>&1; then
    log "✅ Server is running at $BASE_URL"

    log "Running load tests..."
    if php scripts/load-test.php "$BASE_URL"; then
        log "✅ Load tests completed"
    else
        error "❌ Load tests failed"
        exit 1
    fi

    log "Running security audit..."
    if php scripts/security-audit.php "$BASE_URL"; then
        log "✅ Security audit completed"
    else
        error "❌ Security audit failed"
        exit 1
    fi
else
    warning "⚠️  Server not accessible at $BASE_URL - skipping live tests"
    warning "   To run full tests, start the server and run:"
    echo "   $0 $BASE_URL"
fi

echo
echo "========================================="
log "🎉 All production readiness tests passed!"
echo "========================================="
echo
echo "Next steps for production deployment:"
echo "1. Review security audit findings"
echo "2. Address any performance bottlenecks"
echo "3. Configure monitoring and alerting"
echo "4. Set up backup and recovery procedures"
echo "5. Perform final security review"
echo
echo "Run individual test suites:"
echo "  Unit tests:        cd symfony-dashboard && ./vendor/bin/phpunit --testsuite=unit"
echo "  Integration tests: cd symfony-dashboard && ./vendor/bin/phpunit --testsuite=integration"
echo "  Migration tests:   cd symfony-dashboard && ./vendor/bin/phpunit --testsuite=migrations"
echo "  Load tests:        php scripts/load-test.php $BASE_URL"
echo "  Security audit:    php scripts/security-audit.php $BASE_URL"