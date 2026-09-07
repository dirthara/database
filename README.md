# Dirthara Database

## Docker development environment

Requires Docker with Docker Compose. The development image provides PHP 8.5 CLI,
Composer 2.10.3, and PDO.

Build the image and start the PHP container in the background:

```sh
LOCAL_UID=$(id -u) LOCAL_GID=$(id -g) docker compose up -d --build php
```

The container runs as the non-root `developer` user with your host user and group
IDs, so files created in the mounted repository remain editable on the host.
Both IDs default to 1000. Rebuild with the command above when they change.

The container stays running so you can open a shell at any time:

```sh
docker compose exec php bash
```

Run PHP or Composer commands against the mounted repository:

```sh
docker compose exec php php --version
docker compose exec php php --ri PDO
docker compose exec php composer --version
```

Once a `composer.json` is added, install dependencies with:

```sh
docker compose exec php composer install
```

Stop and remove the development container when finished:

```sh
docker compose down
```

PDO is the shared database interface. Database-specific PDO drivers and database
services will be added when integration tests need them.
