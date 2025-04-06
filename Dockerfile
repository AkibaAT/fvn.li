FROM dunglas/frankenphp:php8.4

# Set working directory
WORKDIR /app

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
        postgresql-client \
        redis-tools \
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

COPY storage /app/storage

# Copy configuration files
COPY docker/php.ini ${PHP_INI_DIR}/conf.d/99-octane.ini

# Set permissions and clean up
RUN apt-get -y autoremove \
    && apt-get clean \
    && docker-php-source delete \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && rm -f /var/log/lastlog /var/log/faillog \
    && chown -R www-data:www-data /app

CMD ["php", "artisan", "octane:frankenphp", "--host=0.0.0.0", "--port=80", "--admin-port=2019"]
