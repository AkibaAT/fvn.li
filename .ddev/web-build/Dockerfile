ARG BASE_IMAGE
FROM $BASE_IMAGE

RUN rm -f /etc/apt/sources.list.d/mariadb*.list /etc/apt/sources.list.d/mariadb*.sources \
    && apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends  \
        libnspr4 \
        libnss3 \
        libatk1.0-0 \
        libatk-bridge2.0-0 \
        libatspi2.0-0 \
        libxcomposite1 \
        libxdamage1 \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*
