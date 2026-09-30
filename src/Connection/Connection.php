<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Exception\QueryException;
use Dirthara\Database\Connection\Lock\LockManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Exception\NamedLockException;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Exception\TransactionException;
use Dirthara\Database\Connection\Transaction\TransactionManager;

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

    public function transactions(): TransactionManager;

    public function locks(): LockManager;

    /**
     * @throws TransactionException
     * @throws NamedLockException
     */
    public function disconnect(): void;

    public function name(): string;

    public function driver(): DriverName;
}
