<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Exceptions\TransactionException;

interface Connection
{
    /**
     * @param array<int|string, scalar|null> $parameters
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function execute(string $query, array $parameters = []): Result;

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function lastInsertId(?string $sequence = null): ?string;

    /**
     * @template T
     *
     * @param callable(Connection): T $callback
     *
     * @return T
     */
    public function transaction(callable $callback): mixed;

    /**
     * @throws TransactionException
     */
    public function beginTransaction(): void;

    /**
     * @throws TransactionException
     */
    public function commit(): void;

    /**
     * @throws TransactionException
     */
    public function rollback(): void;

    public function inTransaction(): bool;

    /**
     * @throws TransactionException
     */
    public function disconnect(): void;

    public function name(): string;

    public function driver(): DriverName;
}
