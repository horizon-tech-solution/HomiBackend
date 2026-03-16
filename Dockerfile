FROM dunglas/frankenphp:php8.4.19-bookworm

RUN install-php-extensions \
    pdo \
    pdo_mysql \
    mbstring \
    curl \
    gd \
    openssl

COPY . /app/public

WORKDIR /app/public

RUN composer install --optimize-autoloader --no-scripts --no-interaction

CMD ["frankenphp", "run", "--config", "/app/public/Caddyfile"]