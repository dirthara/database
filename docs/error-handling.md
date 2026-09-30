---
id: error-handling
title: Error handling
sidebar_position: 9
description: The exceptions the package throws, the context each one carries, and how to log it.
---

# Error handling

Every exception the package throws implements the `DatabaseException` interface,
so one catch block covers all of them:

```php
use Dirthara\Database\Exception\DatabaseException;

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

Each concrete exception also extends the SPL exception that describes it, so a
caller that already handles `InvalidArgumentException` or `RuntimeException`
keeps working:

| SPL parent | Means |
| --- | --- |
| `InvalidArgumentException` | The code passed something the package cannot accept. Nothing reached the database. |
| `RuntimeException` | The operation failed while running, or this database cannot run what was asked of it. |

## The exceptions

All of them live in `Dirthara\Database\Exception`.

| Exception | SPL parent | Thrown when |
| --- | --- | --- |
| `ConnectionException` | `RuntimeException` | PDO refuses to open a connection with the configured settings. |
| `InvalidConnectionConfigException` | `InvalidArgumentException` | A missing host or SQLite database, a host, database, or DSN value containing a semicolon, a DSN parameter name that is not an identifier, a malformed charset, a charset or DSN parameters on a driver that has no place for them, or an invalid savepoint prefix. |
| `ConnectionRegistryException` | `InvalidArgumentException` | A driver or connection name registered twice, a connection name that is not configured, or a config whose driver is not registered. |
| `QueryException` | `RuntimeException` | A statement fails to prepare or execute, and on a failed `lastInsertId()`. |
| `TransactionException` | `RuntimeException` | A begin, commit, rollback, or savepoint statement fails; a commit or rollback is attempted with no active transaction; a disconnect is attempted mid-transaction. |
| `ResultException` | `RuntimeException` | A column is selected that the result does not have, or by name on a driver that exposes no column metadata. |
| `InvalidQueryException` | `InvalidArgumentException` | The query cannot be described: an empty table, a null value where a comparison needs one, an insert whose rows disagree on columns, a binding that is not `scalar\|null`, a negative limit or offset, a chunk without an ordering, an offset on a mutation. |
| `InvalidExpressionException` | `InvalidArgumentException` | An empty or malformed name, a name that looks like SQL, an empty alias, or an operator that is not a comparison. |
| `UnsupportedQueryException` | `RuntimeException` | The query is describable but this database or grammar cannot express it: a limited or ordered update or delete on a driver without support, a joined mutation, `FULL JOIN` on MySQL, or a clause the grammar does not know. |
| `GrammarRegistryException` | `InvalidArgumentException` | A query grammar registered twice for a driver, or requested for a driver that has none. |

Catch the specific type when the recovery differs — retrying is reasonable for a
`ConnectionException` and rarely is for a `QueryException`, which usually means
the SQL or the schema is wrong.

:::note
Because connections are lazy, `execute()` can throw a `ConnectionException` or
an `InvalidConnectionConfigException`: it is the call that opens PDO, and a bad
host surfaces there rather than at construction.
:::

## Building a query

The query builder and the grammars validate before anything reaches the
database. An `InvalidQueryException` or `InvalidExpressionException` means the
code is wrong on every database; an `UnsupportedQueryException` means the code
is wrong on *this* one:

```php
$database->table('users')->select('COUNT(*)');
// InvalidExpressionException — wrong everywhere; use selectRaw() or a RawExpression.

$database->table('users')->orderBy('created_at')->limit(1)->delete();
// UnsupportedQueryException on PostgreSQL, SQLite, and SQL Server; fine on MySQL.
```

[Grammars](query-builder/grammars.md) lists which clauses each database refuses.

:::note
These are thrown while the query is compiled, which for `get()`, `first()`,
`count()`, `insert()`, `update()` and `delete()` is when you call them. For
`cursor()` it is when you start iterating, because compilation is deferred until
then.
:::

## Messages

Messages are written by the package, in full sentences, and name what they can
safely name: the connection, the driver, the operation, and the SQLSTATE.

A database driver's own message is never copied into one. A driver can quote the
values of the row that failed — MySQL's duplicate-key message includes the
duplicate value — and a message ends up in logs and error trackers. The driver's
exception is still available through `getPrevious()` when you need it locally.

## Context

Exceptions carry structured diagnostics in the public, read-only `context`
property, as a string-keyed array.

```php
catch (DatabaseException $exception) {
    $this->logger->error(
        $exception->getMessage(),
        array_merge($exception->context, ['exception' => $exception]),
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
| `rollback_failure` | The message of a rollback that failed while handling another exception. See [transactions](transactions.md#rollback-failures). |
| `column`, `columns` | The requested column and the ones the result actually has. |
| `configured` | The configured connection names, when an unknown one was requested. |
| `field` | The DSN field whose value was rejected. The value itself is left out. |
| `parameter` | The DSN parameter name that was rejected. |
| `charset` | The charset that was rejected. |
| `prefix`, `maximum` | The savepoint prefix that was rejected, and the longest one allowed. |

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

## SQLSTATE

Branch on SQLSTATE rather than on the message:

```php
catch (QueryException $exception) {
    if (($exception->context['sqlstate'] ?? null) === '23000') {
        throw new DuplicateUser(previous: $exception);
    }
}
```

`23000` is an integrity constraint violation on every database. `getCode()` is
always `0`; the identifiers are in the context.

## Your own exceptions

Exceptions thrown by your own drivers or middleware do not need to implement
`DatabaseException`, and should not: it describes what this package throws.
Wrap a package exception in one of your own with it as `previous` when a caller
of yours should not have to know which package failed.
