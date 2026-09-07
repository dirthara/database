<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Driver\DriverName;

interface Connection
{
    /**
     * @param array<int|string, scalar|null> $parameters
     */
    public function execute(string $query, array $parameters): Result;

    /**
     * @template T
     *
     * @param callable(Connection): T $callback
     *
     * @return T
     */
    public function transaction(callable $callback): mixed;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function inTransaction(): bool;

    public function disconnect(): void;

    public function driver(): DriverName;
}
