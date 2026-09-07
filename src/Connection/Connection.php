<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Result\Result;

interface Connection
{
    public function execute(string $query, array $parameters): Result;

    public function transaction(callable $callback): mixed;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function inTransaction(): bool;

    public function disconnect(): void;

    public function driver(): DriverName;
}
