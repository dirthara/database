---
id: row-locks
title: Locking rows
sidebar_position: 5
description: Pessimistic row locks on a select, the transaction they need, and how each database honours them.
---

# Locking rows

A pessimistic row lock stops other transactions from changing, or locking, the
rows a select returned until your transaction ends. It is the tool for reserving
database-backed work that several processes compete for: each one locks the rows
it takes, so no two take the same row.

```php
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Database\Query\Sql\LockWait;

$database->transaction(function (ConnectedDatabase $database): void {
    $row = $database
        ->table('jobs')
        ->where('status', '=', 'queued')
        ->orderBy('id')
        ->forUpdate(LockWait::SkipLocked)
        ->first();

    if ($row === null) {
        return;
    }

    $database->table('jobs')->where('id', '=', $row['id'])->update(['status' => 'reserved']);
});
```

## Requesting a lock

| Method | Lock |
| --- | --- |
| `forUpdate(LockWait $wait = LockWait::Wait)` | An exclusive lock: no other transaction can lock the rows for update or for share. |
| `forShare(LockWait $wait = LockWait::Wait)` | A shared lock: other transactions can also lock the rows for share, but not for update. |
| `lock(LockMode $mode, LockWait $wait = LockWait::Wait)` | Either, from a `LockMode`. |
| `withoutLock()` | Removes a lock set earlier. |

The last call wins, and a cloned builder keeps its lock. The lock is a typed
part of the query; there is no way to pass a locking clause as a string.

`LockWait` says what happens when another transaction already holds a
conflicting lock on a row:

| `LockWait` | Behaviour |
| --- | --- |
| `Wait` | Block until the other transaction ends, the database's normal behaviour. |
| `NoWait` | Fail immediately. The statement throws a `QueryException` carrying the database's SQLSTATE and error code. |
| `SkipLocked` | Leave the rows that cannot be locked immediately out of the result, and lock the rest. |

:::caution
`SkipLocked` returns an incomplete view of the table on purpose. Use it to hand
out work, never to read data that has to be complete.
:::

## A lock needs a transaction

A row lock lasts until the transaction ends. Outside one, every statement is its
own transaction, so the lock would be released before your code could use it. A
locked `get()`, `first()`, or `cursor()` outside an explicit transaction
therefore throws a `RowLockException` instead of running.

`toSql()`, `bindings()`, and `compile()` still work outside a transaction, so a
locked query can be inspected. For `cursor()`, the check happens when you start
iterating.

## What can be locked

Only a plain select can carry a lock: `get()`, `first()`, and `cursor()`. Every
other combination throws an `UnsupportedLockException` rather than running
without the lock or locking something other than what was selected:

| Refused | Why |
| --- | --- |
| `exists()`, `count()`, `sum()`, `avg()`, `min()`, `max()` | They return a value computed from rows, not rows; PostgreSQL refuses a lock on an aggregate. |
| `chunk()` | It pages with an offset across several statements, which skipped rows would shift. |
| `insert()`, `insertGetId()`, `update()`, `delete()` | A write locks what it writes; the configured row lock would mean nothing. |
| A union, join, grouping, having condition, or `distinct()` | The database cannot lock them faithfully, or would lock rows from tables you did not mean to lock. |
| A locked query used as a union operand or in `whereExists()` | Lock the outer query instead. |

## How each database locks

| Database | Update | Share | `NoWait` | `SkipLocked` |
| --- | --- | --- | --- | --- |
| MySQL 8 | `FOR UPDATE` | `FOR SHARE` | `NOWAIT` | `SKIP LOCKED` |
| PostgreSQL | `FOR UPDATE` | `FOR SHARE` | `NOWAIT` | `SKIP LOCKED` |
| SQL Server | `WITH (ROWLOCK, XLOCK)` | `WITH (ROWLOCK, REPEATABLEREAD)` | `NOWAIT` hint | `READPAST` hint |
| SQLite | Refused | Refused | Refused | Refused |

SQL Server has no locking clause, so the lock is a table hint after the table
name. Its update lock is `XLOCK` rather than the more common `UPDLOCK`, because
an `UPDLOCK` is compatible with a shared lock and would let a `forShare()` in
another transaction succeed. With `XLOCK`, the update and share modes conflict
exactly as they do on MySQL and PostgreSQL.

:::note
SQL Server refuses `READPAST` in some configurations, such as a session at
`READ COMMITTED` on a database with `READ_COMMITTED_SNAPSHOT` switched on. That
surfaces as a `QueryException`; the lock is never quietly dropped.
:::

SQLite locks the whole database file when a transaction writes, and has no way
to lock individual rows as a select reads them. Any row lock on SQLite throws an
`UnsupportedLockException`, even outside a transaction, so code that needs a row
lock learns that it did not get one and can choose another strategy.

## What a lock does not do

A row lock is not a [named lock](../named-locks.md) either: a row lock protects
the rows a query selected, inside one transaction, while a named lock
coordinates cooperating processes around something larger than a row.

A row lock is not a replacement for a transaction that does the right thing, and
not a replacement for idempotent work. A process can still crash after taking
a row and before finishing with it: the lock ends with the transaction, and the
row is available again. Design the work so that doing it twice is safe, or so
that the status change and the work commit together.
