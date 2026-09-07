# Dirthara Database

## Usage

A `ConnectionConfig` describes one named connection. A `ConnectionFactory` turns
it into a `Connection` using a registered driver, and a `ConnectionManager`
resolves and caches connections by name.

Each driver is constructed with the `TransactionGrammar` its database
understands. MySQL, PostgreSQL, and SQLite take `StandardTransactionGrammar`;
SQL Server needs `SqlServerTransactionGrammar`, which spells savepoints with
`SAVE TRANSACTION` and cannot release them. A grammar names its savepoints with
a `SavepointPrefix`, which defaults to `dirthara`.

```php
$grammar = new StandardTransactionGrammar(new SavepointPrefix());

$manager = new ConnectionManager(
    new ConnectionFactory([new MySqlDriver($grammar), new SQLiteDriver($grammar)]),
    [
        new ConnectionConfig(
            driver: DriverName::MySql,
            name: 'primary',
            host: 'mysql',
            database: 'app',
            username: 'app',
            password: $password,
        ),
    ],
    default: 'primary',
);

$connection = $manager->connection();
```

Parameters are bound by position or by name. A positional list is keyed from
zero; the connection maps it onto the placeholders, which PDO counts from one.

```php
$rows = $connection->execute('SELECT * FROM users WHERE role = ?', [1, 'admin'])->all();
$rows = $connection->execute('SELECT * FROM users WHERE role = :role', ['role' => 'admin'])->all();
```

A `Result` reads the rows once, moving forward only, so one result should be
read with one method. `first()` returns the next row or null, `all()` the
remaining rows, `column()` one column by position or name, and `iterate()`
yields rows without buffering them.

`transaction()` commits when the callback returns and rolls back when it throws,
rethrowing the original exception. Nested calls use savepoints. An exception
raised by the rollback itself never replaces the exception that caused it; it is
recorded under `rollback_failure` in the context instead.

```php
$connection->transaction(function (Connection $connection): void {
    $connection->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);
});
```

Every exception extends `DatabaseException` and carries diagnostic context for a
PSR-3 logger, including the connection name, driver, and operation. Credentials
are never part of it.

A `ConnectionMiddleware` wraps every connection the factory creates, which is
where cross-cutting behaviour such as query logging or retrying a dropped
connection belongs. The first entry becomes the outermost layer.

```php
final class LogQueries implements ConnectionMiddleware
{
    public function wrap(Connection $connection): Connection
    {
        return new LoggingConnection($connection, $this->logger);
    }
}

$factory = new ConnectionFactory([new MySqlDriver($grammar)], [new LogQueries($logger)]);
```

A decorator that wraps `transaction()` must pass itself to the callback rather
than the connection it wraps, otherwise queries inside a transaction bypass the
decoration.

Two settings are driver-specific: `charset` is applied through the DSN on MySQL
and through `client_encoding` on PostgreSQL; SQLite and SQL Server reject it
rather than accept and ignore it. `options` are PDO attributes keyed by the
`PDO::ATTR_*` constants, and they override the defaults the drivers set.

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
