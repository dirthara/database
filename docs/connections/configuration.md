---
id: configuration
title: Connection configuration
sidebar_position: 1
description: Every ConnectionConfig option, its default, and which drivers use it.
---

# Connection configuration

A `ConnectionConfig` describes exactly one named connection. It is a readonly
value object with named constructor arguments and no setters, so a connection
cannot change shape after it is described.

```php
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

$config = new ConnectionConfig(
    driver: DriverName::PostgresSql,
    name: 'reporting',
    host: 'postgres',
    port: 5432,
    database: 'analytics',
    username: 'reporter',
    password: $password,
    charset: 'UTF8',
    options: [PDO::ATTR_TIMEOUT => 5],
);
```

## Options

| Option | Type | Default | Meaning |
| --- | --- | --- | --- |
| `driver` | `DriverName` | *required* | Which database this connection speaks to. Selects the registered driver that opens it. |
| `name` | `string` | `'default'` | The key the [`ConnectionManager`](connection-manager.md) resolves this connection by. Must be unique across the configs you register. |
| `host` | `?string` | `null` | Server hostname or IP. Required by MySQL, PostgreSQL, and SQL Server; ignored by SQLite. |
| `port` | `?int` | `null` | Server port. Falls back to the driver's default when null. Ignored by SQLite. |
| `database` | `?string` | `null` | Database name. Optional for MySQL, PostgreSQL, and SQL Server, which then connect without selecting one. Required by SQLite, where it is a file path or `:memory:`. |
| `username` | `?string` | `null` | Login user. Passed straight to PDO. Not used by SQLite. |
| `password` | `?string` | `null` | Login password. Marked `#[SensitiveParameter]`, so it is hidden in stack traces. Not used by SQLite. |
| `charset` | `?string` | `null` | Client character set. Applied differently per driver, and rejected by two of them — see [driver-specific options](drivers.md#driver-specific-options). |
| `options` | `array<int, mixed>` | `[]` | PDO attributes keyed by the `PDO::ATTR_*` constants. Overrides the defaults the drivers set. |

Every argument except `driver` has a default, and they are all named, so a
config only mentions what it actually needs.

## The charset option

`charset` accepts an identifier only: letters, digits, hyphens, and underscores,
matching `/^[A-Za-z0-9_-]+$/`. Anything else throws a `ConnectionException`
before it can reach a DSN or a `SET` statement. That is deliberate — the value
ends up in SQL that cannot be parameterised.

## The options array

`options` are the fourth argument to PDO's constructor: attributes keyed by
integer constants, not a bag of package settings.

Two defaults are set for every driver:

| Attribute | Value | Why |
| --- | --- | --- |
| `PDO::ATTR_ERRMODE` | `PDO::ERRMODE_EXCEPTION` | Failures throw instead of returning `false`, which is what the exception mapping relies on. |
| `PDO::ATTR_DEFAULT_FETCH_MODE` | `PDO::FETCH_ASSOC` | Rows are string-keyed maps, matching the `Result` return types. |

MySQL adds one more:

| Attribute | Value | Why |
| --- | --- | --- |
| `PDO::ATTR_EMULATE_PREPARES` | `false` | Real server-side prepared statements, so bound parameter types are honoured. |

Anything you pass in `options` wins over all of these.

:::caution
Overriding `PDO::ATTR_ERRMODE` or `PDO::ATTR_DEFAULT_FETCH_MODE` breaks the
guarantees the rest of the package documents. With a silent error mode, driver
failures stop being exceptions; with another fetch mode, `Result` no longer
returns string-keyed rows.
:::

## Diagnostics and debugging

`diagnostics()` returns the subset of the config that is safe to log —
`connection`, `driver`, `host`, `port`, and `database`, with null values
dropped. This is what gets merged into [exception
context](../error-handling.md).

```php
$config->diagnostics();
// ['connection' => 'reporting', 'driver' => 'pgsql', 'host' => 'postgres', 'port' => 5432, 'database' => 'analytics']
```

Dumping the object with `var_dump()` shows every option, but the password is
replaced with `[redacted]` — present or absent is visible, the value is not.
Credentials never appear in diagnostics, exception context, or a dump.
