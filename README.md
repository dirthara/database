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

## Mago

Run all Mago checks through Composer in the PHP container:

```sh
docker compose exec php composer mago
```

Inside the container shell, use `composer mago` directly. Rebuild the PHP image
after pulling changes to its Dockerfile. This command checks formatting, runs the
linter and static analyzer, and checks architecture rules with `mago guard`.
Every check runs even if an earlier check fails, and the command fails if any
check fails. It does not modify files. Architecture rules apply when configured
in `mago.toml`.

Mago 1.47.3 runs through its official Docker image. Only Docker Compose is needed
on the host, and the PHP container does not need to be running. The image is
downloaded automatically on first use.

Check formatting, lint, and analyze the source:

```sh
docker compose run --rm mago fmt --check
docker compose run --rm mago lint
docker compose run --rm mago analyze
```

Apply formatting with:

```sh
docker compose run --rm mago fmt
```

Add missing strict type declarations, then format all source and test files:

```sh
docker compose run --rm mago lint --only strict-types --fix --potentially-unsafe
docker compose run --rm mago fmt
```

The strict types rule reports violations as errors. Its fix needs
`--potentially-unsafe` because strict typing changes PHP's coercion behavior.
Imports are sorted shortest first within separate class, function, and constant
lists, with blank lines between the lists.

Mago runs with user and group IDs 1000 by default so edited files remain owned by
your host account. If your IDs differ, export them before running these commands:

```sh
export LOCAL_UID=$(id -u) LOCAL_GID=$(id -g)
```

The `mago.toml` configuration targets PHP 8.5 and the `src` and `tests` directories, with `vendor`
available for dependency analysis. The `tools` profile keeps Mago out of the
normal background services; explicitly running the service activates it.
