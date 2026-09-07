<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\TransactionManager;
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
     * @throws ConnectionException
     */
    public function transactions(): TransactionManager;

    /**
     * @throws TransactionException
     */
    public function disconnect(): void;

    public function name(): string;

    public function driver(): DriverName;
}
