FROM mcr.microsoft.com/playwright:v1.58.2

# Set working directory
WORKDIR /app

RUN npm init -y \
    && npm i playwright

# Copy application files
COPY generate.js ./

# Make script executable
RUN chmod +x generate.js

# Set default environment variables
ENV BASE_URL=https://fvn.li
ENV OUTPUT_DIR=/output

# Create output directory
RUN mkdir -p /output

# Run the script
CMD ["node", "generate.js"]
