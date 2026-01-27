#!/bin/bash

set -e

# Replace environment variables in nginx configuration
envsubst '${DOMAIN}' < /etc/nginx/nginx.conf.template > /etc/nginx/nginx.conf
envsubst '${DOMAIN}' < /etc/nginx/sites-enabled/dashboard.conf.template > /etc/nginx/sites-enabled/dashboard.conf
envsubst '${DOMAIN}' < /etc/nginx/sites-enabled/webmail.conf.template > /etc/nginx/sites-enabled/webmail.conf

# Create SSL certificates if they don't exist
if [ ! -f "/etc/letsencrypt/live/${DOMAIN}/fullchain.pem" ]; then
    echo "Generating self-signed certificates for ${DOMAIN}..."
    
    mkdir -p /etc/letsencrypt/live/${DOMAIN}
    
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/letsencrypt/live/${DOMAIN}/privkey.pem \
        -out /etc/letsencrypt/live/${DOMAIN}/fullchain.pem \
        -subj "/C=US/ST=State/L=City/O=Organization/CN=${DOMAIN}"
    
    # Also create for webmail subdomain
    mkdir -p /etc/letsencrypt/live/webmail.${DOMAIN}
    cp /etc/letsencrypt/live/${DOMAIN}/privkey.pem /etc/letsencrypt/live/webmail.${DOMAIN}/privkey.pem
    cp /etc/letsencrypt/live/${DOMAIN}/fullchain.pem /etc/letsencrypt/live/webmail.${DOMAIN}/fullchain.pem
fi

# Generate DH parameters if they don't exist
if [ ! -f "/etc/nginx/dhparam.pem" ]; then
    echo "Generating DH parameters (this may take a while)..."
    openssl dhparam -out /etc/nginx/dhparam.pem 2048
fi

# Create MTA-STS policy file
mkdir -p /var/www/html/.well-known/mta-sts
cat > /var/www/html/.well-known/mta-sts.txt << EOF
version: STSv1
mode: enforce
max_age: 604800
mx: mail.${DOMAIN}
EOF

# Create TLS reporting file
cat > /var/www/html/.well-known/tls-reporting.txt << EOF
v=TLSRPTv1; rua=mailto:tls-reports@${DOMAIN}
EOF

# Start cron for Let's Encrypt renewal
if [ ! -f "/etc/cron.d/certbot" ]; then
    echo "0 0 * * * certbot renew --quiet" > /etc/cron.d/certbot
    crond
fi

# Test nginx configuration
nginx -t

echo "Starting nginx..."
exec nginx -g "daemon off;"