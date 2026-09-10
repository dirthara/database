---
id: database
title: The Database entry point
sidebar_label: Database
sidebar_position: 4
description: Resolve a named connection, scope work to one of them, and open a query builder.
---

# The Database entry point

`Database` is the front door. It holds a [`ConnectionManager`](connections/connection-manager.md)
and a `QueryGrammarResolver`, and hands out connections, query builders, and
transactions by name.

```php
use Dirthara\Database\Database;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Query\Grammar\MySqlQueryGrammar;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;

$database = new Database(
    $manager,
    new QueryGrammarResolver([DriverName::MySql->value => new MySqlQueryGrammar()]),
);

$rows = $database->table('users')->where('active', '=', 1)->get();
```

## What it exposes

| Method | Returns | Purpose |
| --- | --- | --- |
| `connection(?string $name = null)` | `Connection` | The named connection, or the default one. |
| `using(?string $connection = null)` | `ConnectedDatabase` | Everything below, bound to one connection. |
| `table(string\|Expression $table, ?string $connection = null)` | `QueryBuilder` | A builder for that table on that connection. |
| `execute(string $query, array $parameters = [], ?string $connection = null)` | `Result` | Raw SQL, bypassing the builder. |
| `transaction(callable $callback, ?string $connection = null)` | `mixed` | Runs the callback in a transaction. |

Every method takes the connection name last and defaults to the manager's
default, so a single-connection application never mentions it.

## Scoping to one connection

Passing the same name to every call gets repetitive:

```php
$database->table('users', 'legacy');
$database->table('orders', 'legacy');
```

`using()` binds it once and returns a `ConnectedDatabase` — one connection paired
with the grammar for its driver:

```php
$legacy = $database->using('legacy');

$legacy->table('users')->get();
$legacy->table('orders')->get();
```

| Method | Returns |
| --- | --- |
| `connection()` | The `Connection` it wraps. |
| `table(string\|Expression $table)` | A `QueryBuilder` for that table. |
| `execute(string $query, array $parameters = [])` | A `Result`. |
| `transaction(callable $callback)` | The callback's return value. |

`connection()` is the escape hatch: use it for anything the scoped object does
not cover, such as [`lastInsertId()`](queries/executing-queries.md#last-insert-id).

## Transactions

The callback receives the `ConnectedDatabase`, so the query builder is available
inside a transaction:

```php
$database->transaction(function (ConnectedDatabase $db): void {
    $db->table('accounts')->where('id', '=', 1)->update(['balance' => 100]);
    $db->table('transactions')->insert(['account_id' => 1, 'amount' => 100]);
});
```

The callback's return value is passed through, it commits when the callback
returns, and it rolls back and rethrows when the callback throws. Nested calls
use savepoints — see [Transactions](transactions.md).

:::caution
Work inside the callback has to go through the object the callback was given.
Reaching back to `$database->table('accounts', 'other')` opens a *different*
connection, which is outside the transaction and commits on its own.
:::

## Resolving grammars

`QueryGrammarResolver` maps a driver onto the grammar that compiles SQL for it.
`table()` resolves it from the connection's own driver, so a query built on a
PostgreSQL connection is compiled by the PostgreSQL grammar without being told.

```php
$grammars = new QueryGrammarResolver([
    DriverName::MySql->value => new MySqlQueryGrammar(),
    DriverName::PostgresSql->value => new PostgresSqlQueryGrammar(),
]);

$grammars->register(DriverName::SQLite, new SQLiteQueryGrammar());
```

Register by `DriverName` or by its string value; both resolve to the same entry.
The constructor takes any `iterable`, so a container can hand it a lazy list.

| Situation | Result |
| --- | --- |
| Driver registered twice | `InvalidArgumentException` — a grammar is already registered for that driver. |
| Driver never registered | `InvalidArgumentException` naming the driver, thrown by `table()` or `using()`. |

Registering twice throws rather than overwriting, matching
[`ConnectionFactory`](connections/connection-manager.md#the-factory)'s behaviour
for duplicate drivers: a duplicate is a configuration mistake, and the second
registration silently winning hides it.

:::note
Only register the grammars you use. Nothing resolves a grammar until a query
builder is opened on that connection.
:::

## Next

- [Building queries](query-builder/building-queries.md) — the builder's clauses.
- [Expressions](query-builder/expressions.md) — when a string is quoted and when
  it is raw SQL.
- [Grammars](query-builder/grammars.md) — what each database supports.
