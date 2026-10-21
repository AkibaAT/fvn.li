FROM oven/bun:1.4.3-alpine@sha256:629e17411f1f129dbec3af78d5af9c9f2a937435c80349437206c6b0b7422373

COPY patch-bun-ca.py /tmp/patch-bun-ca.py
RUN apk upgrade --no-cache \
    && apk add --no-cache ca-certificates \
    && apk add --no-cache --virtual .ca-patch python3 \
    && python3 /tmp/patch-bun-ca.py /usr/local/bin/bun \
    && apk del .ca-patch \
    && rm /tmp/patch-bun-ca.py
