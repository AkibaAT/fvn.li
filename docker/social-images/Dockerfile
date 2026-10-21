FROM mcr.microsoft.com/playwright:v1.64.1-noble@sha256:7852dc5a7d72f5fe458a597d690f6c42fbc8e9e26ddfa95f8e6ef552558d9bbf

RUN apt-get update \
    && apt-get upgrade -y \
    && apt-get install -y --no-install-recommends ca-certificates libnss3-tools \
    && rm -rf /var/lib/apt/lists/*

ENV NODE_OPTIONS=--use-openssl-ca \
    SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt

# Set working directory
WORKDIR /app

# Build context is docker/social-images. Copy dependency manifests first so
# npm ci stays cached when only the generator script changes.
COPY ["package.json", "package-lock.json", "./"]
RUN npm ci --omit=dev --ignore-scripts

# Copy application files
COPY ["generate.js", "./"]

RUN usermod -aG www-data pwuser \
    && mkdir -p /output /home/pwuser/.pki/nssdb \
    && certutil -N -d sql:/home/pwuser/.pki/nssdb --empty-password

# Keep explicit NSS distrust after Node removes these roots from its own bundle.
COPY recalled-ca.pem /tmp/recalled-ca.pem
RUN node <<'JS'
const { X509Certificate } = require('node:crypto');
const { execFileSync } = require('node:child_process');
for (const pem of require('node:fs').readFileSync('/tmp/recalled-ca.pem', 'utf8').match(/-----BEGIN CERTIFICATE-----[\s\S]+?-----END CERTIFICATE-----/g)) {
    const certificate = new X509Certificate(pem);
    if (/^CN=(emSign ECC Root CA - G3|AAA Certificate Services)$/m.test(certificate.subject)) {
        execFileSync('certutil', ['-A', '-a', '-d', 'sql:/home/pwuser/.pki/nssdb',
            '-n', certificate.fingerprint256, '-t', 'p,p,p'], { input: pem });
    }
}
JS

RUN chown -R pwuser:pwuser /output /home/pwuser/.pki

# Set default environment variables
ENV BASE_URL=https://fvn.li
ENV OUTPUT_DIR=/output

# Run the script
CMD ["node", "generate.js"]
