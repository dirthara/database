<?php

declare(strict_types=1);

namespace Dirthara\Database;

use Throwable;
use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Exception\QueryException;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Connection\Lock\AcquiredLock;
use Dirthara\Database\Exception\NamedLockException;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Exception\InvalidLockNameException;
use Dirthara\Database\Exception\UnsupportedLockException;

final readonly class ConnectedDatabase
{
    public function __construct(
        private Connection $connection,
        private QueryGrammar $grammar,
    ) {}

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function table(string|Expression $table): QueryBuilder
    {
        return new QueryBuilder(connection: $this->connection, grammar: $this->grammar, table: $table);
    }

    /**
     * @param array<int|string, scalar|null> $parameters
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function execute(string $query, array $parameters = []): Result
    {
        return $this->connection->execute($query, $parameters);
    }

    /**
     * @template T
     *
     * @param callable(self): T $callback
     *
     * @return T
     *
     * @throws Throwable
     */
    public function transaction(callable $callback): mixed
    {
        return $this->connection->transactions()->run(fn(): mixed => $callback($this));
    }

    /**
     * @throws NamedLockException
     * @throws ConnectionException
     * @throws InvalidLockNameException
     * @throws UnsupportedLockException
     */
    public function acquireLock(string $name): AcquiredLock
    {
        return $this->connection->locks()->acquire($name);
    }

    /**
     * @throws NamedLockException
     * @throws ConnectionException
     * @throws InvalidLockNameException
     * @throws UnsupportedLockException
     */
    public function tryAcquireLock(string $name): ?AcquiredLock
    {
        return $this->connection->locks()->tryAcquire($name);
    }
}
