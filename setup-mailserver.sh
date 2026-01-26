#!/bin/bash

# Setup script for docker-mailserver

set -e

CONFIG_DIR="./data/mailserver/config"

echo "Setting up docker-mailserver..."

# Create necessary directories
mkdir -p $CONFIG_DIR/{postfix,opendkim,opendmarc,dovecot,clamav,spamassassin}

# Copy configuration files
if [ ! -f "$CONFIG_DIR/postfix-accounts.cf" ]; then
    echo "Creating postfix accounts file..."
    touch "$CONFIG_DIR/postfix-accounts.cf"
fi

if [ ! -f "$CONFIG_DIR/postfix-virtual.cf" ]; then
    echo "Creating postfix virtual file..."
    touch "$CONFIG_DIR/postfix-virtual.cf"
fi

if [ ! -f "$CONFIG_DIR/postfix-aliases.cf" ]; then
    echo "Creating postfix aliases file..."
    touch "$CONFIG_DIR/postfix-aliases.cf"
fi

# Generate DKIM keys for each domain
for domain_file in ./data/mailserver/config/domains/*; do
    if [ -f "$domain_file" ]; then
        domain=$(basename "$domain_file")
        echo "Generating DKIM keys for $domain..."
        
        mkdir -p "$CONFIG_DIR/opendkim/keys/$domain"
        
        docker run --rm \
            -v "$CONFIG_DIR/opendkim/keys:/tmp/keys" \
            mailserver/docker-mailserver:latest \
            opendkim-genkey -b 2048 -h rsa-sha256 \
            -r -s mail -d "$domain" \
            -D /tmp/keys/"$domain"
        
        chmod 644 "$CONFIG_DIR/opendkim/keys/$domain/mail.private"
    fi
done

# Configure MTA-STS
echo "Configuring MTA-STS..."
cat > "$CONFIG_DIR/mta-sts.txt" << EOF
version: STSv1
mode: enforce
max_age: 604800
mx: mail.${DOMAIN}
EOF

# Configure TLS reporting
cat > "$CONFIG_DIR/tlsrpt.txt" << EOF
v=TLSRPTv1; rua=mailto:tls-reports@${DOMAIN}
EOF

echo "Mailserver setup completed!"