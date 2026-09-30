<p align="center">
  <img src="logo-no-bg.png" alt="Dirthara" width="480">
</p>

# Dirthara Database

Database connections for the Dirthara framework. A thin layer over PDO that gives you named connections, a query
builder that compiles for MySQL, PostgreSQL, SQLite, and SQL Server, parameter binding, forward-only result sets, and
nested transactions backed by savepoints. Usage documentation lives in [`docs`](docs/intro.md) and is published on the
Dirthara documentation site at <https://dirthara.github.io/docs/>, which documents every package in the framework.

## Installation

Requires PHP `^8.5` (PHP 8.5 or a later PHP 8 release) and the `pdo` extension. Each database also needs its own PDO
extension: `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, or `pdo_sqlsrv`. Install with:

```sh
composer require dirthara/database
```

## Docker development environment

Requires Docker with Docker Compose. The development image provides PHP 8.5 CLI, Composer 2.10.3, Mago 1.47.3, Xdebug,
and a PDO driver for every database the package supports: `pdo_sqlite`, `pdo_mysql`, `pdo_pgsql`, and `pdo_sqlsrv`.

```sh
git clone git@github.com:dirthara/database.git
cd database
LOCAL_UID=$(id -u) LOCAL_GID=$(id -g) docker compose up -d --build php
docker compose exec php composer install
```

The container runs as the non-root `developer` user. The build arguments `LOCAL_UID` and `LOCAL_GID` default to 1000;
the command above uses your host IDs so generated files remain editable. Set `PHP_VERSION` to override the default
8.5 image, which is how CI walks its matrix. Rebuild when the Dockerfile or build arguments change.

`docker compose up -d php` also starts PostgreSQL, MySQL, and SQL Server and waits until each reports healthy, because
the tests run against every driver the package supports. The first start pulls roughly a gigabyte of images, and SQL
Server takes around thirty seconds to accept connections. The SQL Server image is published for amd64 only, so its tests
skip on an arm64 host.

Open a shell or stop the environment with:

```sh
docker compose exec php bash
docker compose down
```

## Tests

```sh
docker compose exec php composer test
```

Tests belong in `tests`, under `Dirthara\Database\Tests`. Source belongs in `src`, under `Dirthara\Database`. Helpers
shared between tests, such as test doubles and the traits that open connections, live in `tests/Fixtures`.

Most of the suite runs against SQLite in memory. Behaviour that needs a real database belongs in `tests/Integration`,
where one conformance suite, the `DriverConformance` trait, runs against every driver: connecting, binding, reading
results, and committing, rolling back, and nesting transactions. Assembling a DSN is not evidence of assembling the
right one, and savepoint grammar cannot be judged without a server that accepts or rejects it.

| Suite | Service | Notes |
| --- | --- | --- |
| `SQLiteConformanceTest` | none | In memory, so it always runs. |
| `PostgresSqlConformanceTest` | `postgres` | Also covers `client_encoding` and a sequence-named `lastInsertId()`. |
| `MySqlConformanceTest` | `mysql` | Also covers the charset the DSN carries. |
| `SqlServerConformanceTest` | `sqlserver` | The only run that exercises `SqlServerTransactionGrammar`. |

The PostgreSQL, MySQL, and SQL Server suites skip when their PDO driver is missing, and read their connection from
`DIRTHARA_POSTGRES_*`, `DIRTHARA_MYSQL_*`, and `DIRTHARA_SQLSRV_*` (`_HOST`, `_PORT`, `_DATABASE`, `_USERNAME`,
`_PASSWORD`), defaulting to the services in `compose.yaml`.

The SQL Server suite passes `TrustServerCertificate=yes` through the config's `dsn` parameters, because ODBC Driver 18
encrypts and verifies by default and the development container presents a self-signed certificate. Production
connections should trust a real certificate chain instead.

## Code quality

Run the same checks as CI:

```sh
docker compose exec php composer ci
```

Run individual checks:

```sh
docker compose exec php composer fmt-check
docker compose exec php composer lint
docker compose exec php composer analyze
docker compose exec php composer guard
```

`composer mago` runs the formatting, import-order, lint, analysis, and architecture checks. Every check runs even when an
earlier one fails. `composer ci` also runs the import sorter's own tests, the test suite, and the coverage gate.

Apply formatting and import sorting with `composer fmt`, or include automatic lint fixes with `composer cs`:

```sh
docker compose exec php composer fmt
docker compose exec php composer cs
```

`composer cs` includes potentially unsafe lint fixes; review its changes.

Run coverage separately with:

```sh
docker compose exec php composer test-coverage
docker compose exec php composer coverage
```

Xdebug is inactive by default and enabled for the coverage run. The report is written to `build/coverage/clover.xml`.
The gate requires 100% line coverage of `src` and lists uncovered lines. It needs the PostgreSQL service running, since
that driver applies a charset only after connecting and no other test reaches those lines.

## Contributing

Each supported version has its own branch; there is no `main`. See [CONTRIBUTING.md](CONTRIBUTING.md) for branching,
release, and pull request requirements, and [AGENTS.md](AGENTS.md) for agent instructions.

## Security

Report vulnerabilities through GitHub's private advisory form rather than in a public issue. See
[SECURITY.md](SECURITY.md) for the supported versions, what is in scope, and what to include in a report.

## License

Copyright (c) 2026 Dirthara. Released under the [MIT License](LICENSE).
