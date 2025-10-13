FROM dunglas/frankenphp:php8.4

# Set working directory
WORKDIR /app

# Install system dependencies
RUN apt-get update \
    && apt-get upgrade -yqq \
    && apt-get install -yqq --no-install-recommends --show-progress \
        apt-transport-https \
        apt-utils \
        ca-certificates \
        curl \
        ffmpeg \
        gpg \
        imagemagick \
        libgl1 \
        libsodium-dev \
        nano \
        ncdu \
        postgresql-client \
        procps \
        redis-tools \
        supervisor \
        unzip \
        wget \
        git \
    && (curl -fsSL https://deb.nodesource.com/setup_22.x | bash -) \
    && (wget -qO - https://packages.adoptium.net/artifactory/api/gpg/key/public | gpg --dearmor | tee /etc/apt/trusted.gpg.d/adoptium.gpg > /dev/null) \
    && (echo "deb https://packages.adoptium.net/artifactory/deb $(awk -F= '/^VERSION_CODENAME/{print$2}' /etc/os-release) main" | tee /etc/apt/sources.list.d/adoptium.list) \
    && apt-get update \
    && apt-get install -yqq --no-install-recommends --show-progress \
        nodejs \
        temurin-21-jdk \
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

ENV JAVA_HOME=/usr/lib/jvm/temurin-21-jdk-amd64
ENV PATH="${JAVA_HOME}/bin:${PATH}"

# Copy storage directory
COPY storage /app/storage

# Copy configuration files
COPY docker/php.ini ${PHP_INI_DIR}/conf.d/99-octane.ini
COPY docker/supervisor /etc/supervisor/conf.d/
COPY docker/Caddyfile /etc/caddy/Caddyfile

# Copy entrypoint script
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# Set permissions and clean up
RUN apt-get -y autoremove \
    && apt-get clean \
    && docker-php-source delete \
    && mkdir -p /var/log/caddy \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* \
    && rm -f /var/log/lastlog /var/log/faillog \
    && chown -R www-data:www-data /app

ENTRYPOINT ["/entrypoint.sh"]
