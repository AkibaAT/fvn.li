FROM dunglas/frankenphp:1.12.2-php8.5.6@sha256:dca5aa94e1be4488d06799785e43128e2cf95bb0f50d00cd51ae43828a390d5b

ARG BUN_VERSION=1.3.13
ARG BUN_LINUX_X64_BASELINE_SHA256=9d8a24292a7068090205daac0a5a223f5f69736f5287e37bf88d3b4031edc750
ARG BUN_LINUX_AARCH64_SHA256=70bae41b3908b0a120e1e58c5c8af30e74afae3b8d11b0d3fdd8e787ddfb4b22

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
        docker-cli \
        ffmpeg \
        gpg \
        graphviz \
        imagemagick \
        libgl1 \
        libsodium-dev \
        nano \
        ncdu \
        pipx \
        postgresql-client \
        procps \
        redis-tools \
        supervisor \
        unzip \
        wget \
        git \
    && PIPX_HOME=/opt/pipx PIPX_BIN_DIR=/usr/local/bin pipx install unrpa \
    && PIPX_HOME=/opt/pipx PIPX_BIN_DIR=/usr/local/bin pipx install rpycdec \
    && ( \
        arch="$(dpkg --print-architecture)" \
        && case "$arch" in \
            amd64) bun_target="bun-linux-x64-baseline"; bun_sha256="$BUN_LINUX_X64_BASELINE_SHA256" ;; \
            arm64) bun_target="bun-linux-aarch64"; bun_sha256="$BUN_LINUX_AARCH64_SHA256" ;; \
            *) echo "Unsupported architecture for Bun: $arch" >&2; exit 1 ;; \
        esac \
        && curl -fsSL "https://github.com/oven-sh/bun/releases/download/bun-v${BUN_VERSION}/${bun_target}.zip" -o /tmp/bun.zip \
        && echo "${bun_sha256}  /tmp/bun.zip" | sha256sum -c - \
        && unzip -q /tmp/bun.zip -d /tmp/bun \
        && install -m 0755 "/tmp/bun/${bun_target}/bun" /usr/local/bin/bun \
        && rm -rf /tmp/bun /tmp/bun.zip \
        && bun --version \
    ) \
    && (wget -qO - https://packages.adoptium.net/artifactory/api/gpg/key/public | gpg --dearmor | tee /etc/apt/trusted.gpg.d/adoptium.gpg > /dev/null) \
    && (echo "deb https://packages.adoptium.net/artifactory/deb $(awk -F= '/^VERSION_CODENAME/{print$2}' /etc/os-release) main" | tee /etc/apt/sources.list.d/adoptium.list) \
    && apt-get update \
    && apt-get install -yqq --no-install-recommends --show-progress \
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
COPY docker/app/php.ini ${PHP_INI_DIR}/conf.d/99-octane.ini
COPY docker/app/supervisor /etc/supervisor/conf.d/
COPY docker/app/Caddyfile /etc/caddy/Caddyfile

# Copy entrypoint script
COPY docker/app/entrypoint.sh /entrypoint.sh
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
