FROM mcr.microsoft.com/playwright:v1.65.1-noble@sha256:1507cbfc550a63a6b7396791ca4f00df17beb56342d2447b3f106c306f525579

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
