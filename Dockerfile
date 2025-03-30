FROM dunglas/frankenphp:php8.4

# Set working directory
WORKDIR /app

# Update Node.js to version 22 (LTS)
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs

# Install system dependencies
RUN apt-get update \
    && apt-get upgrade -yqq \
    && apt-get install -yqq --no-install-recommends --show-progress \
        apt-utils \
        ca-certificates \
        curl \
        ffmpeg \
        imagemagick \
        libgl1 \
        libsodium-dev \
        nano \
        ncdu \
        supervisor \
        unzip \
        wget \
        git \
    # Install PHP extensions
    && install-php-extensions \
        bcmath \
        bz2 \
        exif \
        gd \
        igbinary \
        intl \
        mbstring \
        memcached \
        opcache \
        pcntl \
        pdo_mysql \
        pdo_pgsql \
        pgsql \
        rdkafka \
        redis \
        sockets \
        zip \
    && cp ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini

# Verify Node.js version
RUN node --version && npm --version

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy application
COPY . .

# Install dependencies with cache mount
RUN --mount=type=cache,target=/root/.composer/cache,sharing=locked \
    composer install --no-dev --optimize-autoloader --no-interaction

# Install Node.js dependencies and build assets with cache mount
RUN --mount=type=cache,target=/root/.npm,sharing=locked \
    npm ci \
    && npm run build

# Directory setup and config
RUN mkdir -p storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && php artisan storage:link \
    && php artisan config:cache \
    && php artisan config:clear \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan optimize \
    && php artisan livewire:publish --assets \
    && mv docker/php.ini ${PHP_INI_DIR}/conf.d/99-octane.ini \
    && rm -rf docker \
    && apt-get -y autoremove \
    && apt-get clean \
    && docker-php-source delete \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && rm -f /var/log/lastlog /var/log/faillog \
    && chown -R www-data:www-data /app

ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80", "--admin-port=2019"]
