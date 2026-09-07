<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use PDO;
use PDOException;
use PDOStatement;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Result\PdoResult;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Connection\Transaction\TransactionManager;

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

    /**
     * @param array<int|string, scalar|null> $parameters
     *
     * @throws QueryException
     */
    public function execute(string $query, array $parameters): Result
    {
        try {
            $statement = $this->pdo()->prepare($query);

            if ($statement === false) {
                throw new QueryException('Failed to prepare the query.', context: [
                    'operation' => 'prepare',
                    'query' => $query,
                ]);
            }

            $this->bindParameters($statement, $parameters);

            $statement->execute();

            return new PdoResult($statement);
        } catch (PDOException $exception) {
            throw QueryException::fromPdo(exception: $exception, query: $query);
        }
    }

    public function beginTransaction(): void
    {
        $this->transactions->begin();
    }

    public function commit(): void
    {
        $this->transactions->commit();
    }

    public function rollback(): void
    {
        $this->transactions->rollback();
    }

    public function inTransaction(): bool
    {
        return $this->transactions->inTransaction();
    }

    public function transaction(callable $callback): mixed
    {
        return $this->transactions->run(fn() => call_user_func($callback, $this));
    }

    /**
     * @param array<int|string, scalar|null> $parameters
     */
    private function bindParameters(PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $statement->bindValue($key, $value, $this->inferParameterType($value));
        }
    }

    private function inferParameterType(mixed $value): int
    {
        return match (true) {
            is_int($value) => PDO::PARAM_INT,
            is_bool($value) => PDO::PARAM_BOOL,
            is_null($value) => PDO::PARAM_NULL,
            default => PDO::PARAM_STR,
        };
    }

    public function disconnect(): void
    {
        $this->pdo = null;
    }

    public function driver(): DriverName
    {
        return $this->driver->name();
    }
}
