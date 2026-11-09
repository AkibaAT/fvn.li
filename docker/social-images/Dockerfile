FROM mcr.microsoft.com/playwright:v1.65.0-noble@sha256:6e3fb709361521eb27161606e6da6d2674e5e89fa16516cb37d6fdd91372273d

RUN apt-get update \
    && apt-get upgrade -y \
    && rm -rf /var/lib/apt/lists/*

# Set working directory
WORKDIR /app

# Build context is docker/social-images. Copy dependency manifests first so
# npm ci stays cached when only the generator script changes.
COPY ["package.json", "package-lock.json", "./"]
RUN npm ci --omit=dev --ignore-scripts

# Copy application files
COPY ["generate.js", "./"]

RUN usermod -aG www-data pwuser \
    && mkdir -p /output \
    && chown -R pwuser:pwuser /output

# Set default environment variables
ENV BASE_URL=https://fvn.li
ENV OUTPUT_DIR=/output

# Run the script
CMD ["node", "generate.js"]
