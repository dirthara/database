FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo

COPY --from=composer:2.10.3 /usr/bin/composer /usr/local/bin/composer

WORKDIR /app
