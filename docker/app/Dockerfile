FROM dunglas/frankenphp:1.12.4-php8.5.8@sha256:5aa4ed78d14ff2f16320be8923cbe0cadff553138c8e7712d287abe7450ad64e

ARG BUN_VERSION=1.3.14
ARG BUN_LINUX_X64_BASELINE_SHA256=a063908ae08b7852ca10939bbdc6ceed3ddabce8fb9402dce83d65d73b36e6c7
ARG BUN_LINUX_AARCH64_SHA256=a27ffb63a8310375836e0d6f668ae17fa8d8d18b88c37c821c65331973a19a3b
ARG BUTLER_VERSION=15.29.0
ARG BUTLER_LINUX_AMD64_SHA256=bbb2eb17747aeec5f5d7ec0f0e5ffcd595af075fb5b75336b5b6740916470fde
ARG BUTLER_LINUX_ARM64_SHA256=5268faa7dc0ac71c8137a7691a01430f21d20084759b2ed4bf2b954418b60457

# Set working directory
WORKDIR /app

COPY docker/app/python-tools-requirements.txt /tmp/python-tools-requirements.txt

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
        postgresql-client \
        procps \
        python3-venv \
        redis-tools \
        supervisor \
        util-linux \
        unzip \
        wget \
        git \
    && python3 -m venv /opt/renpy-tools \
    && /opt/renpy-tools/bin/pip install --no-cache-dir --require-hashes -r /tmp/python-tools-requirements.txt \
    && ln -s /opt/renpy-tools/bin/unrpa /usr/local/bin/unrpa \
    && ln -s /opt/renpy-tools/bin/rpycdec /usr/local/bin/rpycdec \
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
    && ( \
        arch="$(dpkg --print-architecture)" \
        && case "$arch" in \
            amd64) butler_arch="amd64"; butler_sha256="$BUTLER_LINUX_AMD64_SHA256" ;; \
            arm64) butler_arch="arm64"; butler_sha256="$BUTLER_LINUX_ARM64_SHA256" ;; \
            *) echo "Unsupported architecture for butler: $arch" >&2; exit 1 ;; \
        esac \
        && curl -fsSL "https://github.com/itchio/butler/releases/download/v${BUTLER_VERSION}/butler-linux-${butler_arch}.zip" -o /tmp/butler.zip \
        && echo "${butler_sha256}  /tmp/butler.zip" | sha256sum -c - \
        && unzip -q /tmp/butler.zip -d /tmp/butler-extract \
        && mkdir -p /opt/butler \
        && cp -a "/tmp/butler-extract/linux-${butler_arch}/." /opt/butler/ \
        && chmod 0755 /opt/butler/butler \
        && ln -sf /opt/butler/butler /usr/local/bin/butler \
        && rm -rf /tmp/butler-extract /tmp/butler.zip \
        && butler -V \
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
