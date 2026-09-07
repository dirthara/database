<?php

namespace Dirthara\Connection;

use Dirthara\Connection\Result\Result;

interface Connection
{
    public function execute(string $query, array $parameters): Result;

    public function transaction(callable $callback): mixed;

    public function beginTransaction(): void;

    public function commit(): void;

    public function rollback(): void;

    public function inTransaction(): bool;

    public function disconnect(): void;

    public function driver(): string;
}