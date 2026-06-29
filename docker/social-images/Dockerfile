FROM mcr.microsoft.com/playwright:v1.61.1-noble@sha256:5b8f294aff9041b7191c34a4bab3ac270157a28774d4b0660e9743297b697e48

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
