FROM docker.io/library/php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    zip \
    unzip \
    supervisor \
    python3 \
    py3-pip \
    docker-cli \
    openssl \
    libxml2-dev \
    linux-headers \
    icu-dev \
    $PHPIZE_DEPS

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    zip \
    exif \
    pcntl \
    gd \
    sockets \
    intl \
    opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

# Install Composer
COPY --from=docker.io/library/composer:latest /usr/bin/composer /usr/bin/composer

# Create working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Create log directory for supervisor
RUN mkdir -p /var/log/supervisor \
    && chown -R www-data:www-data /var/log/supervisor

# Copy supervisor configuration
COPY supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Switch to non-root user
USER www-data

EXPOSE 9000

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]