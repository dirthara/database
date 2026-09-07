---
id: getting-started
title: Getting started
sidebar_position: 3
description: Wire up a driver, a config, and a manager, then run your first query.
---

# Getting started

Three objects stand between you and a query. A `ConnectionConfig` describes a
connection, a `Driver` knows how to open it, and a `ConnectionManager` hands out
the result by name.

## Wire it up

```php
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;

$grammar = new StandardTransactionGrammar(new SavepointPrefix());

$manager = new ConnectionManager(
    new ConnectionFactory([new MySqlDriver($grammar)]),
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

A driver is constructed with the [transaction grammar](transactions.md#grammars)
its database understands. MySQL, PostgreSQL, and SQLite take
`StandardTransactionGrammar`; SQL Server takes `SqlServerTransactionGrammar`.

:::note
Nothing has connected yet. `$manager->connection()` builds a `PdoConnection`
around the config; PDO is constructed on the first query.
:::

## Run a query

```php
$rows = $connection->execute('SELECT id, name FROM users WHERE role = ?', ['admin'])->all();

$connection->execute('INSERT INTO users (name, role) VALUES (?, ?)', ['Ada', 'admin']);

$id = $connection->lastInsertId();
```

`execute()` always returns a [`Result`](queries/results.md), for writes as well
as reads. For a write, `affectedRows()` is the interesting part.

## Wrap it in a transaction

```php
$connection->transactions()->run(function () use ($connection): void {
    $connection->execute('INSERT INTO accounts (name) VALUES (?)', ['Ada']);
    $connection->execute('UPDATE totals SET accounts = accounts + 1');
});
```

The callback's return value is passed through. It commits when the callback
returns and rolls back when it throws, rethrowing the original exception.
Nested calls use savepoints.

## A minimal SQLite setup

SQLite needs no host or credentials, which makes it the shortest way to try the
package out — and the way its own test suite runs.

```php
use Dirthara\Database\Connection\Driver\SQLiteDriver;

$manager = new ConnectionManager(
    new ConnectionFactory([new SQLiteDriver($grammar)]),
    [new ConnectionConfig(driver: DriverName::SQLite, database: ':memory:')],
);

$connection = $manager->connection();
$connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
```

The config leaves `name` at its default of `default`, which is also the
manager's default connection name, so `connection()` resolves it without
arguments.

## Next

- [Connection configuration](connections/configuration.md) — every option and
  what it means.
- [Executing queries](queries/executing-queries.md) — parameter binding rules.
- [Error handling](error-handling.md) — which exception comes from where.
