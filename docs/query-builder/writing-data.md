---
id: writing-data
title: Writing data
sidebar_position: 3
description: Insert, update, and delete rows, and the clauses a mutation refuses.
---

# Writing data

`insert()`, `update()` and `delete()` compile and run immediately, returning the
number of affected rows.

```php
$database->table('users')->insert(['name' => 'Ada', 'active' => 1]);

$database->table('users')->where('id', '=', 7)->update(['active' => 0]);

$database->table('users')->where('active', '=', 0)->delete();
```

## Inserting

One row is an associative array; several rows are a list of them.

```php
$database->table('users')->insert(['name' => 'Ada']);

$database->table('users')->insert([
    ['name' => 'Ada', 'active' => 1],
    ['name' => 'Grace', 'active' => 0],
]);
```

The column list comes from the first row, and every other row has to carry the
same columns in the same order:

```php
$database->table('users')->insert([
    ['name' => 'Ada', 'active' => 1],
    ['active' => 0, 'name' => 'Grace'],
]);
// InvalidArgumentException: Every inserted row needs the same columns in the same order.
```

:::note
The alternative would be to reorder each row against the first, which quietly
turns a mismatched row into a row of misplaced values. Refusing is louder and
the fix is one line at the call site.
:::

An empty array inserts nothing and returns `0` without touching the database. A
null value is a value — `['name' => null]` binds null, which is what a nullable
column wants.

Values must be `scalar|null`. Anything else throws when the query is compiled,
so encode an array and format a `DateTimeInterface` before you insert it.

To read back a generated key, use the connection:

```php
$database->table('users')->insert(['name' => 'Ada']);

$id = $database->connection()->lastInsertId();
```

## Updating

`update()` takes the columns to set and applies the builder's conditions.

```php
$database->table('users')
    ->where('active', '=', 0)
    ->update(['active' => 1, 'confirmed_at' => $now]);
```

An empty array updates nothing and returns `0`. As with insert, the values are
`scalar|null`.

:::danger
`update()` with no condition updates every row, and so does `delete()`. Neither
requires a `where()`, because "set a flag on everything" is a real query — but
it means a forgotten condition is a full-table write.
:::

## Deleting

```php
$database->table('sessions')->where('expires_at', '<', $cutoff)->delete();
```

## Which clauses a mutation accepts

A mutation uses the table, the conditions, and — where the database supports
them — a limit and an ordering. Anything else is refused rather than ignored.

| Clause | On an update or delete |
| --- | --- |
| `where*()` | Always. |
| `limit()` | MySQL and SQL Server only. Others throw. |
| `orderBy*()` | MySQL only. Others throw. |
| `offset()` | Never. Always throws. |
| `join*()` | Not supported yet. Always throws. |

```php
// MySQL: deletes the oldest matching row
$database->table('users')->where('active', '=', 0)->orderBy('created_at')->limit(1)->delete();
```

:::caution
This is the part worth knowing before you rely on it. An ordered, limited delete
is a MySQL feature. On PostgreSQL, SQLite, or SQL Server the same code throws a
`LogicException` at compile time rather than deleting an arbitrary row, so a
query that works in development will not silently pick the wrong row in
production on another database.
:::

The exception names what it could not do:

```
A limited delete query is not supported by this driver.
An ordered delete query is not supported by this driver.
Delete queries cannot skip rows with an offset.
Joined delete queries are not supported yet.
```

Why each one behaves that way is in [Grammars](grammars.md#mutations). The short
version: SQL Server can limit with `TOP (n)` but cannot order; PostgreSQL has
neither; SQLite has both only when its library was compiled with an optional
flag, so relying on it would work on one machine and fail on the next.

`offset()` is refused by the builder rather than by a grammar, because no
supported database can express it on a mutation. To delete all but the newest
rows, select the keys first and delete by them:

```php
$keep = $database->table('sessions')->select('id')->orderByDesc('created_at')->limit(100)->get();

$database->table('sessions')->whereNotIn('id', array_column($keep, 'id'))->delete();
```

## Raw statements

For anything the builder does not cover — `TRUNCATE`, an upsert, a `RETURNING`
clause — go through the connection or `Database::execute()`:

```php
$database->execute('TRUNCATE TABLE sessions');

$id = $database->execute('INSERT INTO users (name) VALUES (?) RETURNING id', ['Ada'])
    ->first()['id'];
```

See [Executing queries](../queries/executing-queries.md) for the binding rules
that apply there.
