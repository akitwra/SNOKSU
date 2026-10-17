FROM composer:2.10.3 AS composer
FROM php:8.4.26-cli
RUN --mount=type=secret,id=cloud_ca set -eu; if [ -f /run/secrets/cloud_ca ]; then cp /run/secrets/cloud_ca /etc/ssl/certs/ca-certificates.crt; fi; sed -i 's|http://deb.debian.org|https://deb.debian.org|g' /etc/apt/sources.list.d/debian.sources && apt-get update && apt-get install -y --no-install-recommends libpq-dev libonig-dev libxml2-dev libzip-dev unzip git \
    && docker-php-ext-install -j4 pdo_pgsql mbstring dom xml xmlwriter zip bcmath pcntl opcache \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
ENV COMPOSER_HOME=/tmp/composer
WORKDIR /app
EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
