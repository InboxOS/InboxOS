FROM docker.io/library/nginx:alpine

# Install certbot and tools
RUN apk add --no-cache \
    certbot \
    certbot-nginx \
    openssl \
    bash \
    gettext

# Copy configuration
COPY nginx.conf.template /etc/nginx/nginx.conf.template
COPY nginx.http.conf.template /etc/nginx/nginx.http.conf.template
COPY sites/ /etc/nginx/sites-enabled/

# Create SSL directory
RUN mkdir -p /etc/letsencrypt/live

# Copy entrypoint script
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80 443 25 587 465 993 995

ENTRYPOINT ["/entrypoint.sh"]
CMD ["nginx", "-g", "daemon off;"]