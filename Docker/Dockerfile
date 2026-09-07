FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-install pdo

COPY --from=composer:2.10.3 /usr/bin/composer /usr/local/bin/composer

ARG LOCAL_UID=1000
ARG LOCAL_GID=1000

RUN groupadd --gid "${LOCAL_GID}" developer \
    && useradd --uid "${LOCAL_UID}" --gid developer --create-home developer

USER developer

WORKDIR /app
