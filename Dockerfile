FROM dunglas/frankenphp:php8.4.19-bookworm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    zip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN install-php-extensions \
    pdo \
    pdo_mysql \
    mbstring \
    curl \
    gd \
    openssl \
    zip

# Install composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY . /app/public

WORKDIR /app/public

# Remove committed vendor folder and reinstall clean
RUN rm -rf vendor && composer install --optimize-autoloader --no-scripts --no-interaction

CMD ["frankenphp", "run", "--config", "/app/public/Caddyfile"]