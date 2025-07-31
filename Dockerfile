FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install mysqli pdo pdo_mysql zip

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Make start script executable
RUN chmod +x start.sh

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Expose port (Railway will override this)
EXPOSE $PORT

# Start using the start script
CMD ["./start.sh"] 