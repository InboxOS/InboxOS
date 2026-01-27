#!/bin/bash

# Monitoring script for mail server

set -e

LOG_FILE="/var/log/mailserver-monitor.log"
HEALTH_URL="http://localhost/health"
MAILSERVER_CONTAINER="mailserver"
DASHBOARD_CONTAINER="mail-dashboard"

log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $*" >> "$LOG_FILE"
}

# Check dashboard health
log "Checking dashboard health..."
if curl -f -s "$HEALTH_URL" > /dev/null 2>&1; then
    log "✅ Dashboard is healthy"
else
    log "❌ Dashboard is unhealthy"
    # Send alert (you can integrate with your alerting system here)
fi

# Check mailserver container
log "Checking mailserver container..."
if podman ps | grep -q "$MAILSERVER_CONTAINER"; then
    log "✅ Mailserver container is running"
else
    log "❌ Mailserver container is not running"
fi

# Check disk space
DISK_USAGE=$(df / | tail -1 | awk '{print $5}' | sed 's/%//')
log "Disk usage: ${DISK_USAGE}%"
if [ "$DISK_USAGE" -gt 90 ]; then
    log "⚠️  High disk usage detected"
fi

# Check mail queue
QUEUE_SIZE=$(podman exec "$MAILSERVER_CONTAINER" mailq 2>/dev/null | grep -c "^[A-F0-9]" || echo "0")
log "Mail queue size: $QUEUE_SIZE"
if [ "$QUEUE_SIZE" -gt 100 ]; then
    log "⚠️  Large mail queue detected"
fi

# Check active connections
ACTIVE_CONNECTIONS=$(podman exec "$MAILSERVER_CONTAINER" netstat -an 2>/dev/null | grep -c ":25 " || echo "0")
log "Active SMTP connections: $ACTIVE_CONNECTIONS"

log "Monitoring check completed"