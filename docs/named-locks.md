---
id: named-locks
title: Named locks
sidebar_position: 9
description: Session-level advisory locks that let cooperating processes take turns, and how each database provides them.
---

# Named locks

A named lock lets cooperating processes agree that only one of them does
something at a time: one migration runner, one scheduled rebuild, one process
working through a batch. It is exclusive, it is held by one database session,
and it has nothing to do with rows.

```php
$lock = $database->tryAcquireLock('reports:nightly-rebuild');

if ($lock === null) {
    return; // Another process is already rebuilding.
}

try {
    $this->rebuild();
} finally {
    $lock->release();
}
```

## Named locks and row locks

The package has two locking tools, and they solve different problems:

| | [Row lock](query-builder/row-locks.md) | Named lock |
| --- | --- | --- |
| Protects | The rows a select returned. | Nothing in the database; it is a flag cooperating processes agree to honour. |
| Lasts | Until the transaction ends. | Until it is released, or the session ends. |
| Needs a transaction | Yes. | No, and commits and rollbacks do not affect it. |
| Held by | The transaction. | The database session of one Dirthara connection. |

Named locks are advisory. They only stop code that asks for the same lock; a
process that does not ask can still read and write whatever it likes.

## Acquiring and releasing

| Method | Returns | Behaviour |
| --- | --- | --- |
| `acquireLock(string $name)` | `AcquiredLock` | Waits until the lock is free, however long that takes. |
| `tryAcquireLock(string $name)` | `?AcquiredLock` | Takes the lock if it is free, and returns `null` straight away if another session holds it. |

Both are on `ConnectedDatabase`, and on `Database` with an optional connection
name as the second argument. Underneath, they call the connection's lock
manager, which is also available directly:

```php
$lock = $connection->locks()->acquire('reports:nightly-rebuild');
$held = $connection->locks()->held(); // ['reports:nightly-rebuild']
```

`null` from `tryAcquireLock()` only ever means another session holds the lock.
A failed statement, an unsupported driver, or a result the database should not
have returned throws instead.

:::caution
`acquireLock()` has no timeout. If the holder never releases the lock and its
session never ends, the call waits forever. Use `tryAcquireLock()` when waiting
is not an option.
:::

An `AcquiredLock` carries the logical `name` it was acquired with and whether
it has been `released`. `release()` gives the lock back:

- It runs on the exact database session that acquired the lock. It never opens a
  new session to do so.
- Releasing a lock a second time throws a `NamedLockException` and sends nothing
  to the database.
- If the release statement fails, it throws a `NamedLockException` and the lock
  counts as released: the connection stops tracking it, and the database
  releases it when the session ends.

Release explicitly, in a `finally` block. There is no destructor that releases a
forgotten lock, because a destructor that talks to the database can run at a
point where the connection is already gone.

## One session, one holder

A named lock belongs to the database session of the connection that acquired
it:

- **Not reentrant.** Acquiring or trying for a lock the same connection already
  holds throws a `NamedLockException`. The databases would each count a second
  acquisition differently, so the package refuses it rather than let the count
  decide when the lock is really free.
- **Independent between connections.** Two connections are two sessions, even
  in the same process, and contend for the same lock like two processes would.
- **Gone with the session.** When the session ends — the process exits, the
  connection is dropped, or the server kills it — the database releases every
  named lock it held.
- **Not affected by transactions.** A named lock acquired inside a transaction
  that rolls back is still held afterwards.

A connection that holds named locks refuses to `disconnect()`, throwing a
`NamedLockException` that lists them, for the same reason it refuses while a
transaction is active: disconnecting would silently release locks that live
`AcquiredLock` objects still claim to hold. Release the locks first.

## Lock names

A lock name is any nonempty string. An empty name throws an
`InvalidLockNameException`.

The databases limit and truncate their own lock identifiers, so the package
never passes a name through as it is. It maps each name to a fixed-size native
identifier with SHA-256, so a long name cannot be cut down to collide with
another, and different names stay different locks to the limits of the hash.
The `AcquiredLock` keeps the name you gave it.

Namespace your names, for example `package:purpose`, because every application
sharing the database shares the lock names.

## How each database provides them

| Database | Acquire | Try | Release | Native identifier |
| --- | --- | --- | --- | --- |
| MySQL 8 | `GET_LOCK(name, -1)` | `GET_LOCK(name, 0)` | `RELEASE_LOCK(name)` | 64 hex characters of SHA-256 over the configured database and the name. |
| PostgreSQL | `pg_advisory_lock(key)` | `pg_try_advisory_lock(key)` | `pg_advisory_unlock(key)` | A `bigint` from the first 8 bytes of SHA-256 over the name. |
| SQL Server | `sp_getapplock` with `@LockTimeout = -1` | `sp_getapplock` with `@LockTimeout = 0` | `sp_releaseapplock` | `dirthara:` and 64 hex characters of SHA-256 over the name. |
| SQLite | Refused | Refused | — | — |

Every one of them is a session-level lock: MySQL user-level locks,
PostgreSQL session advisory locks, and SQL Server application locks owned by the
`Session`. PostgreSQL and SQL Server scope locks to the current database. MySQL
user-level locks are server-wide, so the package includes the configured
database in the identifier to give MySQL the same scope.

SQLite has no session-level lock of its own. Emulating one would mean wrapping
your code in a transaction, which changes what your code's own transactions do,
or keeping a lock file or table the package would then have to clean up. Both
acquire methods throw an `UnsupportedLockException` on SQLite instead, so a
caller can pick its own strategy.

## What a named lock is not

A named lock coordinates processes that share one database server. It is not
distributed consensus: it does not survive a failover to another server, and a
process whose session is lost stops holding the lock without being told until
its next statement fails. Keep the work it protects safe to repeat, and keep
using transactions for the changes the work makes.
