---
id: getting-started
title: Getting started
sidebar_position: 3
description: Wire up a driver, a config, a manager, and a grammar, then run your first query.
---

# Getting started

Four objects stand between you and a query. A `ConnectionConfig` describes a
connection, a `Driver` knows how to open it, a `ConnectionManager` hands out the
result by name, and a `QueryGrammarResolver` says which grammar compiles SQL for
which database.

## Wire it up

```php
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Database;
use Dirthara\Database\Query\Grammar\MySqlQueryGrammar;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;

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

$database = new Database(
    $manager,
    new QueryGrammarResolver([DriverName::MySql->value => new MySqlQueryGrammar()]),
);
```

A driver is constructed with the [transaction grammar](transactions.md#grammars)
its database understands. MySQL, PostgreSQL, and SQLite take
`StandardTransactionGrammar`; SQL Server takes `SqlServerTransactionGrammar`.

:::note
Nothing has connected yet. Building the `Database`, the manager, or the resolver
never opens a socket; PDO is constructed on the first query.
:::

## Build a query

```php
$rows = $database->table('users')
    ->select('id', 'name')
    ->where('role', '=', 'admin')
    ->orderBy('name')
    ->get();

$database->table('users')->insert(['name' => 'Ada', 'role' => 'admin']);
```

The builder compiles for whichever database the connection speaks. See
[Building queries](query-builder/building-queries.md) for every clause.

## Or write the SQL yourself

```php
$rows = $database->execute('SELECT id, name FROM users WHERE role = ?', ['admin'])->all();

$database->execute('INSERT INTO users (name, role) VALUES (?, ?)', ['Ada', 'admin']);

$id = $database->connection()->lastInsertId();
```

`execute()` always returns a [`Result`](queries/results.md), for writes as well
as reads. For a write, `affectedRows()` is the interesting part.

## Wrap it in a transaction

```php
$database->transaction(function (ConnectedDatabase $db): void {
    $db->table('accounts')->insert(['name' => 'Ada']);
    $db->execute('UPDATE totals SET accounts = accounts + 1');
});
```

The callback receives a [`ConnectedDatabase`](database.md#scoping-to-one-connection)
bound to the connection the transaction is running on, so the builder is
available inside it. The callback's return value is passed through. It commits
when the callback returns and rolls back when it throws, rethrowing the original
exception. Nested calls use savepoints.

## A minimal SQLite setup

SQLite needs no host or credentials, which makes it the shortest way to try the
package out — and the way its own test suite runs.

```php
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;

$database = new Database(
    new ConnectionManager(
        new ConnectionFactory([new SQLiteDriver($grammar)]),
        [new ConnectionConfig(driver: DriverName::SQLite, database: ':memory:')],
    ),
    new QueryGrammarResolver([DriverName::SQLite->value => new SQLiteQueryGrammar()]),
);

$database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
```

The config leaves `name` at its default of `default`, which is also the
manager's default connection name, so `connection()` resolves it without
arguments.

## Next

- [Database](database.md) — named connections and how to scope work to one.
- [Building queries](query-builder/building-queries.md) — the builder's clauses.
- [Expressions](query-builder/expressions.md) — when a string is quoted and when
  it is raw SQL.
- [Connection configuration](connections/configuration.md) — every option and
  what it means.
- [Executing queries](queries/executing-queries.md) — parameter binding rules.
- [Error handling](error-handling.md) — which exception comes from where.
