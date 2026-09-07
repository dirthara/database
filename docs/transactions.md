---
id: transactions
title: Transactions
sidebar_position: 6
description: Run a callback in a transaction, nest it with savepoints, or drive begin and commit yourself.
---

# Transactions

`transactions()` returns the connection's `TransactionManager`. There is one per
connection, created on first request, and it tracks the nesting level.

```php
interface TransactionManager
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function inTransaction(): bool;

    public function level(): int;

    public function run(callable $callback): mixed;
}
```

## Running a callback

`run()` is the way to use a transaction. It commits when the callback returns
and rolls back when it throws, rethrowing the original exception.

```php
$connection->transactions()->run(function () use ($connection): void {
    $connection->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);
    $connection->execute('UPDATE totals SET users = users + 1');
});
```

The callback's return value is passed through, so a transaction can produce a
value:

```php
$id = $connection->transactions()->run(function () use ($connection): string {
    $connection->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);

    return $connection->lastInsertId();
});
```

The callback takes no arguments. It closes over the connection it needs, which
is what keeps [middleware](connections/middleware.md#the-transaction-callback-caveat)
from being bypassed inside a transaction.

## Nesting

Nested `run()` calls do not open a second transaction — the outer one is still
open. The inner call takes a savepoint instead, and rolls back to it if it
throws.

```php
$connection->transactions()->run(function () use ($connection): void {
    $connection->execute('INSERT INTO orders (reference) VALUES (?)', [$reference]);

    try {
        $connection->transactions()->run(fn(): Result => $connection->execute(
            'INSERT INTO order_notes (order_id, note) VALUES (?, ?)',
            [$connection->lastInsertId(), $note],
        ));
    } catch (QueryException) {
        // The note was rolled back. The order is still there.
    }
});
```

`level()` reports the depth: `0` outside a transaction, `1` inside the
outermost, and one more per nested call. `inTransaction()` is the same
information as a boolean.

:::caution
The level is this manager's own count, not the database's. Issuing `BEGIN`,
`COMMIT`, or `SAVEPOINT` as a query through `execute()` changes the database's
state without changing the count, and the two disagree from then on. Use the
manager for transaction control.
:::

## Driving it manually

`begin()`, `commit()`, and `rollback()` are there for control that does not fit
a callback — a transaction that spans several method calls, or one that is
committed by a different object than the one that opened it.

```php
$transactions = $connection->transactions();

$transactions->begin();

try {
    $connection->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);
    $transactions->commit();
} catch (Throwable $exception) {
    $transactions->rollback();

    throw $exception;
}
```

At level 0, `begin()` starts a real transaction; above it, it creates a
savepoint. `commit()` and `rollback()` mirror that: at level 1 they commit or
roll back the transaction, above it they release or roll back to the savepoint.

Committing or rolling back with no active transaction throws a
`TransactionException` rather than passing the call to the driver.

Prefer `run()` where you can. The manual form is a `finally` away from leaking
an open transaction for the rest of the request.

## Rollback failures

If the rollback itself fails — the connection dropped, which is often *why* the
callback threw — the rollback's exception never replaces the exception that
caused it. That would swap the diagnosis for a symptom. It is recorded under
`rollback_failure` in the [context](error-handling.md) of the original exception
instead, when that exception is a `DatabaseException`.

```php
try {
    $connection->transactions()->run($work);
} catch (DatabaseException $exception) {
    $context = $exception->getContext();
    // ['connection' => 'primary', ..., 'rollback_failure' => 'MySQL server has gone away']
}
```

The nesting level is restored to what it was before the failed `run()` either
way, so a failed rollback does not leave the manager convinced it is still
several levels deep.

## Grammars

Savepoint SQL is not standard, so a driver is constructed with the
`TransactionGrammar` its database understands.

```php
interface TransactionGrammar
{
    public function savepointName(int $level): string;

    public function createSavepoint(string $name): string;

    public function releaseSavepoint(string $name): ?string;

    public function rollbackToSavepoint(string $name): string;
}
```

| Grammar | For | Savepoints |
| --- | --- | --- |
| `StandardTransactionGrammar` | MySQL, PostgreSQL, SQLite | `SAVEPOINT`, `RELEASE SAVEPOINT`, `ROLLBACK TO SAVEPOINT` |
| `SqlServerTransactionGrammar` | SQL Server | `SAVE TRANSACTION`, `ROLLBACK TRANSACTION` |

SQL Server has no way to release a savepoint, so its grammar returns null from
`releaseSavepoint()` and the manager skips the statement — committing a nested
level is bookkeeping only. The savepoint is discarded when the outermost
transaction commits.

## Savepoint names

A grammar names its savepoints from a `SavepointPrefix` and the nesting level:
`dirthara1`, `dirthara2`, and so on.

```php
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;

new SavepointPrefix();            // 'dirthara'
new SavepointPrefix('app_tx');    // 'app_tx1', 'app_tx2', ...
```

Change it only if something else in your system already uses savepoints named
`dirthara…`. Because a savepoint name cannot be a bound parameter, the prefix is
validated on construction:

- It must start with a letter or underscore and contain only letters, digits,
  and underscores.
- It must be at most 24 characters.

Either violation throws a `TransactionException` where the prefix is
constructed, long before it can reach a `SAVEPOINT` statement.

One prefix instance can be shared by every grammar; it is readonly and holds
nothing but the string.
