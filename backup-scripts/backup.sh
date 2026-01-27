#!/bin/bash

# Backup script for mail server

set -e

BACKUP_DIR="/backup-scripts/archives"
DATA_DIR="/var/lib/mysql"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p "$BACKUP_DIR"

# Backup MySQL database
if ping -c 1 mail-mysql &> /dev/null; then
  echo "Backing up MySQL database..."
  # Use container hostname "mail-mysql" as defined in docker-compose, not just "mysql"
  mysqldump --host=mail-mysql --user=mailadmin --password="$MYSQL_PASSWORD" \
      --all-databases --single-transaction \
      | gzip > "$BACKUP_DIR/mysql_backup_$TIMESTAMP.sql.gz"
else
  echo "⚠️  MySQL container not reachable. Skipping DB backup."
fi

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

# Verify backups
echo "Verifying backups..."
for backup_file in "$BACKUP_DIR"/*"$TIMESTAMP"*.gz; do
    if [ -f "$backup_file" ]; then
        echo "Verifying $backup_file..."
        if ! gzip -t "$backup_file"; then
            echo "ERROR: Backup file $backup_file is corrupted!"
            exit 1
        fi
        echo "✅ $backup_file is valid"
    fi
done

# Create backup manifest
MANIFEST_FILE="$BACKUP_DIR/backup_manifest_$TIMESTAMP.txt"
cat > "$MANIFEST_FILE" << EOF
Backup completed at: $(date)
Backup timestamp: $TIMESTAMP
Files backed up:
$(ls -la "$BACKUP_DIR"/*"$TIMESTAMP"*)
Backup verification: PASSED
EOF

echo "Backup completed successfully!"