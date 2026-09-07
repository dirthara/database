<?php

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Transaction\TransactionManager;
use PDO;

final class PdoConnection implements Connection
{
    private ?PDO $pdo = null;

    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly Driver $driver,
        private readonly TransactionManager $transactions,
    ) {}

    private function pdo(): PDO
    {
        return $this->pdo ??= $this->driver->connect($this->config);
    }

    public function execute(string $query, array $parameters): Result
    {
        // TODO: Implement execute() method.
    }

    public function transaction(callable $callback): mixed
    {
        // TODO: Implement transaction() method.
    }

    public function beginTransaction(): void
    {
        // TODO: Implement beginTransaction() method.
    }

    public function commit(): void
    {
        // TODO: Implement commit() method.
    }

    public function rollback(): void
    {
        // TODO: Implement rollback() method.
    }

    public function inTransaction(): bool
    {
        // TODO: Implement inTransaction() method.
    }

    public function disconnect(): void
    {
        // TODO: Implement disconnect() method.
    }

    public function driver(): string
    {
        // TODO: Implement driver() method.
    }
}