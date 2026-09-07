# Dirthare Database

## Docker development environment

Requires Docker with Docker Compose. The development image provides PHP 8.5 CLI,
Composer 2.10.3, and PDO.

Build the image:

```sh
docker compose build
```

Run PHP or Composer commands against the mounted repository:

```sh
docker compose run --rm php php --version
docker compose run --rm php php --ri PDO
docker compose run --rm php composer --version
```

Once a `composer.json` is added, install dependencies with:

```sh
docker compose run --rm php composer install
```

PDO is the shared database interface. Database-specific PDO drivers and database
services will be added when integration tests need them.
