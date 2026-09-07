---
id: executing-queries
title: Executing queries
sidebar_position: 1
description: The Connection interface, positional and named parameter binding, and last insert IDs.
---

# Executing queries

`Connection` is the interface you spend your time in.

```php
interface Connection
{
    public function execute(string $query, array $parameters = []): Result;

    public function lastInsertId(?string $sequence = null): ?string;

    public function transactions(): TransactionManager;

    public function disconnect(): void;

    public function name(): string;

    public function driver(): DriverName;
}
```

There is no `query()`, `select()`, or `insert()`. Every statement goes through
`execute()`, which prepares it, binds the parameters, runs it, and returns a
[`Result`](results.md) — for writes as well as reads.

```php
$result = $connection->execute('SELECT id, name FROM users WHERE role = ?', ['admin']);

$rows = $result->all();
```

## Binding parameters

Parameters are bound by position or by name.

A positional list is keyed from zero, the way PHP arrays are. The connection
maps it onto the placeholders, which PDO counts from one, so you never adjust
for the offset:

```php
$connection->execute('SELECT * FROM users WHERE role = ? AND active = ?', ['admin', true]);
```

Named parameters use string keys, with or without the leading colon:

```php
$connection->execute('SELECT * FROM users WHERE role = :role', ['role' => 'admin']);
```

Do not mix the two styles in one query. PDO does not support it, and the failure
comes from the driver rather than from here.

:::caution
Only values can be bound. A table name, a column name, an `ORDER BY` direction,
or the contents of an `IN` list cannot be a placeholder. Build those from a
whitelist you control, never from a request:

```php
$placeholders = implode(', ', array_fill(0, count($ids), '?'));

$connection->execute("SELECT * FROM users WHERE id IN ($placeholders)", $ids);
```
:::

## Parameter types

Parameters are `scalar|null`, and the PDO type is inferred from the PHP type:

| PHP value | Bound as |
| --- | --- |
| `int` | `PDO::PARAM_INT` |
| `bool` | `PDO::PARAM_BOOL` |
| `null` | `PDO::PARAM_NULL` |
| Everything else, including `float` | `PDO::PARAM_STR` |

Floats bind as strings, which is what PDO does with them anyway; the database
casts on the way in. Objects and arrays are not scalars — convert a `DateTimeInterface`
to a string, and encode an array, before binding.

Because the drivers disable emulated prepares where the database supports it,
these types reach the server as types rather than as interpolated text.

## Last insert ID

```php
$connection->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);

$id = $connection->lastInsertId();
```

The value is a string, or null when the driver has none to report. Strings
rather than integers because a 64-bit ID does not always fit a PHP int on every
platform, and some databases hand back non-numeric keys.

PostgreSQL needs the sequence name to answer, since the value comes from a
sequence and not from the connection:

```php
$id = $connection->lastInsertId('users_id_seq');
```

The portable alternative is to ask the database for it. PostgreSQL and SQL
Server can return the key from the statement that generated it:

```php
$id = $connection->execute('INSERT INTO users (name) VALUES (?) RETURNING id', ['Ada'])->first()['id'];
```

## Statements without result rows

DDL and writes return a `Result` too. It has no rows; what it has is a count:

```php
$affected = $connection->execute('DELETE FROM sessions WHERE expires_at < ?', [$cutoff])->affectedRows();

$connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY, name TEXT)');
```

Ignoring the returned `Result` is fine. Nothing is left open by not reading it.

## Identity

`name()` and `driver()` report which connection this is and which database it
speaks to, which is useful in [middleware](../connections/middleware.md) and
when writing SQL that has to differ per database:

```php
$now = $connection->driver() === DriverName::SqlServer ? 'GETDATE()' : 'CURRENT_TIMESTAMP';
```

## Disconnecting

`disconnect()` drops the PDO handle. The connection is reusable afterwards — the
next query opens a new one. It throws a `TransactionException` when a
transaction is still active, rather than discarding uncommitted work.

Prefer `ConnectionManager::disconnect()`, which also drops the connection from
the manager's cache. Calling `disconnect()` on the connection directly leaves
the manager handing out the same instance.
