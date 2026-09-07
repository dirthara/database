---
id: drivers
title: Drivers
sidebar_position: 2
description: The four shipped drivers, the DSN each one builds, and where they differ.
---

# Drivers

A `Driver` does two things: it opens a `PDO` instance for a config, and it names
the [transaction grammar](../transactions.md#grammars) its database understands.

```php
interface Driver
{
    public function name(): DriverName;

    public function transactionGrammar(): TransactionGrammar;

    public function connect(ConnectionConfig $config): PDO;
}
```

Each driver is constructed with its grammar and registered with the
[factory](connection-manager.md#the-factory):

```php
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\Driver\PostgresSqlDriver;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Connection\Driver\SqlServerDriver;
use Dirthara\Database\Connection\Transaction\SqlServerTransactionGrammar;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;

$prefix = new SavepointPrefix();
$standard = new StandardTransactionGrammar($prefix);

$drivers = [
    new MySqlDriver($standard),
    new PostgresSqlDriver($standard),
    new SQLiteDriver($standard),
    new SqlServerDriver(new SqlServerTransactionGrammar($prefix)),
];
```

Register only the drivers you use. A driver you never register costs you a
`ConnectionException` when a config asks for it, not a silent fallback.

## `DriverName`

The enum is backed by PDO's own driver strings, so its values match what
`PDO::getAvailableDrivers()` reports.

| Case | Value | Class |
| --- | --- | --- |
| `DriverName::MySql` | `mysql` | `MySqlDriver` |
| `DriverName::PostgresSql` | `pgsql` | `PostgresSqlDriver` |
| `DriverName::SqlServer` | `sqlsrv` | `SqlServerDriver` |
| `DriverName::SQLite` | `sqlite` | `SQLiteDriver` |

## What each driver requires

| Driver | `host` | `port` default | `database` | `charset` | `dsn` |
| --- | --- | --- | --- | --- | --- |
| MySQL | Required | `3306` | Optional | DSN, defaults to `utf8mb4` | Appended |
| PostgreSQL | Required | `5432` | Optional | `SET client_encoding` | Appended |
| SQL Server | Required | Server default | Optional | Rejected | Appended |
| SQLite | Ignored | — | Required | Rejected | Rejected |

An empty or whitespace-only `host` counts as missing. A `host` or `database`
containing a semicolon is refused, because a semicolon separates DSN fields and
a value containing one could smuggle in another field.

### MySQL

Builds `mysql:host=…;port=…;charset=…`, appending `;dbname=…` when a database is
configured. The charset goes into the DSN, which is the only place MySQL applies
it to the handshake. Without one, the DSN says `utf8mb4`.

### PostgreSQL

Builds `pgsql:host=…;port=…`, appending `;dbname=…` when a database is
configured. A charset is applied after connecting, with
`SET client_encoding TO '…'`, because the pgsql DSN has no charset field.

### SQL Server

Builds `sqlsrv:Server=…`, appending `,port` to the server when a port is
configured and `;Database=…` when a database is. SQL Server uses a comma for the
port rather than a separate DSN field. It has no default port here; leave `port`
null to use the server's own.

### SQLite

Builds `sqlite:` followed by the database value verbatim — a file path, or
`:memory:` for a throwaway in-memory database. No host, port, username, or
password is passed. `database` is required and must be explicit, so a missing
path never quietly becomes an in-memory database whose writes disappear.

## Driver-specific options

Three options are not uniform across drivers.

**`charset`** is applied where the database actually accepts it: through the DSN
on MySQL, through `client_encoding` on PostgreSQL. SQLite and SQL Server throw a
`ConnectionException` instead of accepting a value they would ignore — SQLite
stores text as UTF-8 regardless, and the SQL Server DSN has no equivalent
field. A rejected charset is a configuration mistake worth hearing about.

**`options`** are PDO attributes and reach PDO's constructor unchanged. They
override the defaults each driver sets. See [the options
array](configuration.md#the-options-array).

**`dsn`** parameters are appended to the connection string of every driver that
has one. SQLite's DSN is a bare path, so it refuses them. See [driver-specific
DSN parameters](configuration.md#driver-specific-dsn-parameters).

## Writing a driver

Extend `PdoDriver` rather than implementing `Driver` directly. It handles the
work that is the same everywhere: wrapping `PDOException` in a
`ConnectionException` with context, merging default PDO options, and validating
DSN fragments. Implement `name()` and `createConnection()`.

```php
final class MariaDbDriver extends PdoDriver
{
    public function name(): DriverName
    {
        return DriverName::MySql;
    }

    protected function createConnection(ConnectionConfig $config): PDO
    {
        $dsn = 'mysql:host=' . $this->requireHost($config)->value;

        return new PDO($dsn, $config->username, $config->password, $this->options($config));
    }
}
```

The protected helpers available to a subclass:

| Helper | Purpose |
| --- | --- |
| `options($config)` | Default PDO attributes merged with the config's, config winning. Override to add your own defaults. |
| `requireHost($config)` | The host as a validated `DsnValue`, throwing when it is empty or contains a semicolon. |
| `optionalDatabase($config)` | The database as a validated `DsnValue`, or null when it is absent. |
| `charset($config)` | The charset as a validated `Charset`, or null when it is absent. |
| `rejectCharset($config)` | Throws when a charset is configured. For databases that have no place to put one. |
| `dsnParameters($config)` | The configured driver-specific parameters as a `;Name=Value` string, with both halves validated. Append it last. |
| `rejectDsnParameters($config)` | Throws when any is configured. For a DSN with no `Key=Value` syntax. |
| `context($config, $operation, $cause)` | Exception context: config diagnostics, the operation, and the SQLSTATE and driver code of a `PDOException`. |

Only `createConnection()` needs to run; `connect()` already catches
`PDOException` around it and rethrows it as a `ConnectionException` carrying the
message, SQLSTATE, and driver error code.
