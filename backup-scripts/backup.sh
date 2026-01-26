#!/bin/bash

# Backup script for mail server

set -e

BACKUP_DIR="/backup/archives"
DATA_DIR="/backup/data"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup MySQL database
echo "Backing up MySQL database..."
mysqldump --host=mysql --user=mailadmin --password="$MYSQL_PASSWORD" \
    --all-databases --single-transaction \
    | gzip > "$BACKUP_DIR/mysql_backup_$TIMESTAMP.sql.gz"

# Backup mail data
echo "Backing up mail data..."
tar czf "$BACKUP_DIR/maildata_backup_$TIMESTAMP.tar.gz" \
    -C "$DATA_DIR" mailserver/

# Backup configuration
echo "Backing up configuration..."
tar czf "$BACKUP_DIR/config_backup_$TIMESTAMP.tar.gz" \
    -C "$DATA_DIR" dashboard/ \
    -C "$DATA_DIR" nginx/

# Backup certificates
echo "Backing up certificates..."
if [ -d "/etc/letsencrypt" ]; then
    tar czf "$BACKUP_DIR/certificates_backup_$TIMESTAMP.tar.gz" \
        -C /etc letsencrypt/
fi

# Cleanup old backups (keep 30 days)
find "$BACKUP_DIR" -name "*.gz" -mtime +30 -delete

echo "Backup completed successfully!"