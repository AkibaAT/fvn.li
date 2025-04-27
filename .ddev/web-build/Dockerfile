ARG BASE_IMAGE
FROM $BASE_IMAGE

RUN ARCH=$(dpkg --print-architecture) && \
    if [ "$ARCH" = "amd64" ]; then \
        wget --no-check-certificate -c --header "Cookie: oraclelicense=accept-securebackup-cookie" \
        https://download.oracle.com/java/21/latest/jdk-21_linux-x64_bin.deb -O /tmp/jdk-21.deb; \
    elif [ "$ARCH" = "arm64" ]; then \
        wget --no-check-certificate -c --header "Cookie: oraclelicense=accept-securebackup-cookie" \
        https://download.oracle.com/java/21/latest/jdk-21_linux-aarch64_bin.deb -O /tmp/jdk-21.deb; \
    else \
        echo "Unsupported architecture: $ARCH"; \
        exit 1; \
    fi && \
    apt-get update && \
    apt-get install -y --no-install-recommends /tmp/jdk-21.deb && \
    rm /tmp/jdk-21.deb && \
    if [ "$ARCH" = "amd64" ]; then \
        ln -s /usr/lib/jvm/jdk-21.0.7-oracle-x64 /usr/lib/jvm/jdk-21-oracle; \
    else \
        ln -s /usr/lib/jvm/jdk-21.0.7-oracle-aarch64 /usr/lib/jvm/jdk-21-oracle; \
    fi && \
    apt-get clean && \
    rm -rf /var/lib/apt/lists/*

ENV JAVA_HOME=/usr/lib/jvm/jdk-21-oracle
ENV PATH="${JAVA_HOME}/bin:${PATH}"
