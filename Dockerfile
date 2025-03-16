# Stage 1: Composer dependencies
FROM composer:2 AS composer-builder
WORKDIR /app
COPY . ./
RUN apk add --virtual build-dependencies --no-cache \
        autoconf \
        gcc \
        g++ \
        make \
        freetype-dev \
        zlib-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        libmcrypt-dev \
        openssl \
        ca-certificates \
        libxml2-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --enable-gd --with-freetype=/usr/include/freetype2/ --with-jpeg=/usr/include/ \
    && docker-php-ext-install gd \
    && docker-php-ext-enable gd \
    && composer install --no-dev --optimize-autoloader --no-interaction --ignore-platform-reqs

# Stage 2: Node.js dependencies and frontend build
FROM node:22 AS frontend-builder
WORKDIR /app
COPY --from=composer-builder /app /app
RUN npm ci \
    && npm run build

# Stage 3: Final image
FROM dunglas/frankenphp:php8.4
# Install PHP extensions and dependencies
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
    && cp ${PHP_INI_DIR}/php.ini-production ${PHP_INI_DIR}/php.ini \
    && apt-get -y autoremove \
    && apt-get clean \
    && docker-php-source delete \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && rm -f /var/log/lastlog /var/log/faillog

WORKDIR /app

# Copy built assets from frontend-builder
COPY --from=frontend-builder /app /app

# Directory setup and config
RUN mkdir -p storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/testing \
        bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache \
    && php artisan optimize \
    && php artisan storage:link \
    && php artisan config:clear \
    && php artisan livewire:publish --assets \
    && mv docker/php.ini ${PHP_INI_DIR}/conf.d/99-octane.ini \
    && rm -rf docker \
    && chown -R www-data:www-data /app

ENTRYPOINT ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80", "--admin-port=2019"]
