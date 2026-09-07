# Dirthara Database

Database connections for the Dirthara framework. A thin layer over PDO that
gives you named connections, parameter binding, forward-only result sets, and
nested transactions backed by savepoints.

## Installation

```sh
composer require dirthara/database
```

The package requires PHP 8.5 and the `pdo` extension. Each driver also needs its
own PDO extension: `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, or `pdo_sqlsrv`.

Usage documentation lives in [`docs`](docs), which is published as a Docusaurus
site by a separate package.

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

Install dependencies with:

```sh
docker compose exec php composer install
```

Stop and remove the development container when finished:

```sh
docker compose down
```

PDO is the shared database interface. The base image provides `pdo_sqlite`, which
the test suite uses. Driver extensions and database services for MySQL,
PostgreSQL, and SQL Server will be added when integration tests need them.

## Tests

Run the suite through Composer in the PHP container:

```sh
docker compose exec php composer test
```

The suite runs against SQLite in memory, so it needs no database service. Tests
that cover the MySQL, PostgreSQL, and SQL Server drivers assert on
configuration handling and DSN validation, which happen before PDO is asked to
connect.

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

`composer lint` reports lint violations without touching files. `composer
lint-fix` applies the fixes it can, including the potentially unsafe ones.

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

## Security

Report vulnerabilities privately through GitHub's advisory form rather than in a
public issue. See [SECURITY.md](SECURITY.md) for the supported versions, what is
in scope, and what to include in a report.

## License

Released under the MIT License. See [LICENSE](LICENSE).
