ARG BASE_IMAGE
FROM $BASE_IMAGE

RUN (wget -qO - https://packages.adoptium.net/artifactory/api/gpg/key/public | gpg --dearmor | tee /etc/apt/trusted.gpg.d/adoptium.gpg > /dev/null) \
    && (echo "deb https://packages.adoptium.net/artifactory/deb $(awk -F= '/^VERSION_CODENAME/{print$2}' /etc/os-release) main" | tee /etc/apt/sources.list.d/adoptium.list) \
    && apt-get update \
    && apt-get install -y --no-install-recommends temurin-21-jdk \
    && ARCH=$(dpkg --print-architecture) \
    && if [ "$ARCH" = "amd64" ]; then \
        ln -s /usr/lib/jvm/temurin-21-jdk-amd64 /usr/lib/jvm/jdk-21; \
    else \
        ln -s /usr/lib/jvm/temurin-21-jdk-arm64 /usr/lib/jvm/jdk-21; \
    fi \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

ENV JAVA_HOME=/usr/lib/jvm/jdk-21
ENV PATH="${JAVA_HOME}/bin:${PATH}"
