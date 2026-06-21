FROM mcr.microsoft.com/playwright:v1.61.0-noble@sha256:57b65fdc9ceabe0ef613124c7bbe2babcf9362c4d85e382fe3b03604e84b428a

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
    && chown pwuser:pwuser /output

# Set default environment variables
ENV BASE_URL=https://fvn.li
ENV OUTPUT_DIR=/output

# Run the script
CMD ["node", "generate.js"]
