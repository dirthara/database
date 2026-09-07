---
id: connection-manager
title: Factory and manager
sidebar_label: Factory and manager
sidebar_position: 3
description: Build connections from configs and resolve them by name, lazily and cached.
---

# Factory and manager

The factory builds a connection from a config. The manager decides which config
that is and remembers the result.

## The factory

`ConnectionFactory` is constructed with the drivers it may use and, optionally,
the [middleware](middleware.md) that wraps everything it creates.

```php
use Dirthara\Database\Connection\ConnectionFactory;

$factory = new ConnectionFactory($drivers, $middleware);

$connection = $factory->create($config);
```

Both arguments are `iterable`, so a generator or a lazy service-container
collection works as well as an array.

Drivers are keyed by their `DriverName`. Registering the same driver name twice
throws a `ConnectionException` at construction rather than letting one
registration silently shadow the other. Asking for a config whose driver was
never registered throws a `ConnectionException` from `create()`.

`create()` returns a fresh `Connection` on every call. Caching is the manager's
job.

## The manager

`ConnectionManager` holds the configs, resolves one by name, and caches the
connection it produced.

```php
use Dirthara\Database\Connection\ConnectionManager;

$manager = new ConnectionManager($factory, $configs, default: 'primary');
```

| Argument | Type | Default | Meaning |
| --- | --- | --- | --- |
| `factory` | `ConnectionFactory` | *required* | Builds a connection from a config. |
| `configs` | `iterable<ConnectionConfig>` | *required* | The configured connections, keyed internally by their `name`. |
| `default` | `string` | `'default'` | The name used when a connection is requested without one. |

Configuring the same connection name twice throws a `ConnectionException` at
construction. The `default` name is not checked at construction; asking for a
connection that was never configured throws from `connection()`, with the names
that *are* configured in its context.

### Methods

```php
$manager->connection();            // the default connection
$manager->connection('reporting'); // a connection by name

$manager->names();                 // ['primary', 'reporting']

$manager->disconnect('reporting');
$manager->disconnectAll();
```

| Method | Behaviour |
| --- | --- |
| `connection(?string $name = null)` | Returns the named connection, building it on first request and returning the same instance afterwards. Falls back to the default name. Throws `ConnectionException` when the name is not configured. |
| `disconnect(?string $name = null)` | Closes the connection and drops it from the cache, so the next request builds a new one. A no-op when that connection was never built. |
| `disconnectAll()` | Disconnects every connection that has been built. |
| `names()` | The configured connection names, in the order they were registered. |

Disconnecting throws a `TransactionException` when a transaction is still
active on that connection, rather than dropping the handle and losing the
uncommitted work.

## Laziness

Two things are deferred, and they are worth keeping apart.

The manager defers **building** a connection: `connection('reporting')` is the
first time `ConnectionFactory::create()` runs for that name. The connection
defers **opening** it: PDO is constructed on the first `execute()`,
`lastInsertId()`, or transaction operation.

```php
$connection = $manager->connection('reporting'); // no socket yet
$connection->transactions();                     // still no socket
$connection->execute('SELECT 1');                // now it connects
```

So a config for a database this request never touches costs nothing, and a
misconfigured host is not an error until something needs it. Registering ten
connections in a container is fine.

## Using one connection

The manager exists for applications with more than one database. With a single
connection, the factory is enough:

```php
$connection = $factory->create($config);
```

You give up the caching and the disconnect bookkeeping, so hold on to the
returned instance rather than calling `create()` per query — each call builds a
connection that opens its own PDO handle.
