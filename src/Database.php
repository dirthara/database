<?php

declare(strict_types=1);

namespace Dirthara\Database;

use Throwable;
use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

final readonly class Database
{
    public function __construct(
        private ConnectionManager $connections,
        private QueryGrammarResolver $grammars,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function connection(?string $name = null): Connection
    {
        return $this->connections->connection($name);
    }

    /**
     * @throws ConnectionException
     */
    public function using(?string $connection = null): ConnectedDatabase
    {
        $connection = $this->connection($connection);

        return new ConnectedDatabase(connection: $connection, grammar: $this->grammars->resolve($connection->driver()));
    }

    /**
     * @throws ConnectionException
     */
    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        return $this->using($connection)->table($table);
    }

    /**
     * @param array<int|string, scalar|null> $parameters
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function execute(string $query, array $parameters = [], ?string $connection = null): Result
    {
        return $this->connection($connection)->execute($query, $parameters);
    }

    /**
     * @template T
     *
     * @param callable(ConnectedDatabase): T $callback
     *
     * @return T
     *
     * @throws ConnectionException
     * @throws Throwable
     */
    public function transaction(callable $callback, ?string $connection = null): mixed
    {
        return $this->using($connection)->transaction($callback);
    }
}
