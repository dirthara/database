---
id: middleware
title: Middleware
sidebar_position: 4
description: Wrap every connection the factory creates to add logging, retries, or metrics.
---

# Middleware

A `ConnectionMiddleware` wraps every connection the factory creates. This is
where cross-cutting behaviour belongs — query logging, timing, retrying a
dropped connection — instead of in a driver or at every call site.

```php
interface ConnectionMiddleware
{
    public function wrap(Connection $connection): Connection;
}
```

## Registering middleware

Middleware is the factory's second argument:

```php
$factory = new ConnectionFactory([new MySqlDriver($grammar)], [new LogQueries($logger)]);
```

The first entry becomes the outermost layer, so a list reads in the order the
layers see a query. With `[$logging, $retrying]`, logging wraps retrying: the
log records one query, and the retry inside it is invisible to the log. Swap
them and every attempt is logged separately.

Every connection the factory creates goes through every middleware. There is no
per-connection registration; a middleware that should only apply to some
connections can check `$connection->name()` or `$connection->driver()` and
return the connection unwrapped.

## Writing a middleware

The middleware itself is usually trivial. The decorator does the work.

```php
final readonly class LogQueries implements ConnectionMiddleware
{
    public function __construct(
        private LoggerInterface $logger,
    ) {}

    public function wrap(Connection $connection): Connection
    {
        return new LoggingConnection($connection, $this->logger);
    }
}
```

A decorator implements `Connection` and forwards what it does not care about:

```php
final readonly class LoggingConnection implements Connection
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {}

    public function execute(string $query, array $parameters = []): Result
    {
        $this->logger->debug('Executing query.', ['connection' => $this->name(), 'query' => $query]);

        return $this->connection->execute($query, $parameters);
    }

    public function lastInsertId(?string $sequence = null): ?string
    {
        return $this->connection->lastInsertId($sequence);
    }

    public function transactions(): TransactionManager
    {
        return $this->connection->transactions();
    }

    public function disconnect(): void
    {
        $this->connection->disconnect();
    }

    public function name(): string
    {
        return $this->connection->name();
    }

    public function driver(): DriverName
    {
        return $this->connection->driver();
    }
}
```

Do not log parameter values. They are user data and frequently credentials,
tokens, or personal information — the same reason [exception
context](../error-handling.md) carries the query but not its bindings.

## The transaction callback caveat

:::danger
A wrapping connection has to pass **itself** to a `transaction()` callback
instead of forwarding the callback unchanged. Forwarding it hands the callback
the wrapped connection, so queries inside the transaction skip the wrapper.
:::

If your decorator exposes a convenience method that takes a callback and hands a
connection to it, that connection must be `$this`:

```php
public function transaction(callable $callback): mixed
{
    // Correct: the callback runs against this decorator.
    return $this->connection->transactions()->run(fn(): mixed => $callback($this));
}
```

Forwarding `$callback` straight into `run()` looks equivalent and is not: every
query inside the transaction would go to the inner connection, unlogged and
unretried. The `TransactionManager` returned by `transactions()` takes a
callback with no arguments, which sidesteps the problem entirely — nothing is
handed to the callback, so the callback keeps using whatever connection it
already closed over.
