---
id: error-handling
title: Error handling
sidebar_position: 7
description: The exception hierarchy, the context each exception carries, and how to log it.
---

# Error handling

Every exception the package throws extends `DatabaseException`, so one catch
block covers the package:

```php
use Dirthara\Database\Exceptions\DatabaseException;

try {
    $rows = $connection->execute($query, $parameters)->all();
} catch (DatabaseException $exception) {
    // Handle, or rethrow as something your domain understands.
}
```

Errors are exceptions, never return values. The drivers set
`PDO::ATTR_ERRMODE` to `PDO::ERRMODE_EXCEPTION`, and a `PDOException` is caught
and rethrown as the package exception that fits, with the original as
`getPrevious()`.

## The hierarchy

```
Exception
└── DatabaseException
    ├── ConnectionException
    ├── QueryException
    ├── TransactionException
    └── ResultException
```

| Exception | Thrown when |
| --- | --- |
| `ConnectionException` | A connection cannot be built or opened: a driver or connection name that is not registered, a duplicate registration, a missing or malformed host or database, a charset the driver rejects, or a failure from PDO's constructor. |
| `QueryException` | A statement fails to prepare or execute, and on a failed `lastInsertId()`. |
| `TransactionException` | A begin, commit, rollback, or savepoint statement fails; a commit or rollback is attempted with no active transaction; a disconnect is attempted mid-transaction; a savepoint prefix is invalid. |
| `ResultException` | A column is selected that the result does not have, or by name on a driver that exposes no column metadata. |

Catch the specific type when the recovery differs — retrying is reasonable for a
`ConnectionException` and rarely is for a `QueryException`, which usually means
the SQL or the schema is wrong.

:::note
Because connections are lazy, `execute()` can throw a `ConnectionException`:
it is the call that opens PDO, and a bad host surfaces there rather than at
construction.
:::

## Context

Exceptions carry structured diagnostics instead of formatting them into the
message. `getContext()` returns them as a string-keyed array.

```php
catch (DatabaseException $exception) {
    $this->logger->error(
        $exception->getMessage(),
        array_merge($exception->getContext(), ['exception' => $exception]),
    );
}
```

That is the intended shape: pass the context to a PSR-3 logger and add the
caught exception under the `exception` key. Merge in that order rather than with
`+`, so the caught exception ends up under that key even when the context
already has an `exception` entry.

The package never logs and never depends on a logger. It collects the facts; the
application decides what to do with them.

`addContext()` merges more in and returns the same instance, which is how a
value object's exception picks up the connection it came from as it travels
outward:

```php
throw $exception->addContext(['request_id' => $requestId]);
```

### Keys

| Key | Meaning |
| --- | --- |
| `connection` | The configured connection name. |
| `driver` | The driver value, for example `mysql`. |
| `host`, `port`, `database` | From the config, omitted when null. |
| `operation` | What was being attempted, as an `Operation` value. |
| `query` | The SQL, on prepare and execute failures. Never the bound parameters. |
| `sqlstate` | The five-character SQLSTATE from the driver, when it reported one. |
| `driver_code` | The database's own error number, when it reported one. |
| `rollback_failure` | The message from a rollback that failed while handling another exception. See [transactions](transactions.md#rollback-failures). |
| `column`, `columns` | The requested column and the ones the result actually has. |
| `configured` | The configured connection names, when an unknown one was requested. |
| `field`, `value` | The DSN field that was rejected and the value that was rejected. |
| `charset` | The charset that was rejected. |
| `prefix` | The savepoint prefix that was rejected. |

:::danger
Context never contains passwords, credential-bearing DSNs, or bound parameter
values. Keep it that way in your own decorators and drivers — context is
written to logs, and logs are read by more people than a database password
should be.
:::

### The `operation` values

`operation` is the `Operation` enum, which names the step that failed. Useful
for grouping errors in a log without parsing messages.

| Value | Step |
| --- | --- |
| `connect` | Opening the PDO handle. |
| `disconnect` | Dropping the handle. |
| `prepare` | Preparing a statement. |
| `execute` | Executing a statement. |
| `last_insert_id` | Reading the last insert ID. |
| `column` | Selecting a column from a result. |
| `begin`, `commit`, `rollback` | Transaction control at the outermost level. |
| `savepoint`, `release_savepoint`, `rollback_to_savepoint` | Transaction control at a nested level. |

## Codes and SQLSTATE

`getCode()` carries the driver's error code when it is an integer. PDO reports
SQLSTATE as a string in that slot, so the code is frequently `0` and the useful
identifier is in the context:

```php
catch (QueryException $exception) {
    if (($exception->getContext()['sqlstate'] ?? null) === '23000') {
        throw new DuplicateUser(previous: $exception);
    }
}
```

Branch on SQLSTATE rather than on the message. Messages come from the driver and
change between versions; `23000` is an integrity constraint violation
everywhere.

## Custom exceptions

If you extend the hierarchy in your own drivers or middleware, follow the
package convention: extend `DatabaseException`, keep `context` as the fourth
constructor argument after message, code, and previous, and carry data rather
than logging it.

```php
final class ReplicaLagException extends DatabaseException {}

throw new ReplicaLagException('The replica is too far behind.', context: [
    'connection' => $connection->name(),
    'operation' => Operation::Connect->value,
    'lag_seconds' => $lag,
]);
```

Named constructors are worth it once a message is built from its context, so
that the message and the keys stay in one place:

```php
public static function tooFarBehind(string $connection, int $lag): self
{
    return new self(
        sprintf('The replica "%s" is %d seconds behind.', $connection, $lag),
        context: ['connection' => $connection, 'lag_seconds' => $lag],
    );
}
```
