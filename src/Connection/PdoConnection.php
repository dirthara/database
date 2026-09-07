<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use PDO;
use PDOException;
use PDOStatement;
use Dirthara\Database\Connection\Pdo\PdoError;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Connection\Result\PdoResult;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\TransactionManager;
use Dirthara\Database\Connection\Exceptions\TransactionException;
use Dirthara\Database\Connection\Transaction\PdoTransactionManager;

use function is_int;
use function is_bool;
use function is_null;
use function array_merge;

final class PdoConnection implements Connection
{
    private ?PDO $pdo = null;
    private ?TransactionManager $transactions = null;

    public function __construct(
        private readonly ConnectionConfig $config,
        private readonly Driver $driver,
    ) {}

    /**
     * @throws ConnectionException
     */
    private function pdo(): PDO
    {
        return $this->pdo ??= $this->driver->connect($this->config);
    }

    public function transactions(): TransactionManager
    {
        return $this->transactions ??= new PdoTransactionManager(
            $this->pdo(...),
            $this->driver->transactionGrammar(),
            $this->config,
        );
    }

    /**
     * @param array<int|string, scalar|null> $parameters
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function execute(string $query, array $parameters = []): Result
    {
        try {
            $statement = $this->pdo()->prepare($query);

            if ($statement === false) {
                throw new QueryException('Failed to prepare the query.', context: $this->context(Operation::Prepare, [
                    'query' => $query,
                ]));
            }

            $this->bindParameters($statement, $parameters);

            if ($statement->execute() === false) {
                throw new QueryException('Failed to execute the query.', context: $this->context(Operation::Execute, [
                    'query' => $query,
                ]));
            }

            return new PdoResult($statement);
        } catch (PDOException $exception) {
            throw new QueryException(
                message: $exception->getMessage(),
                code: PdoError::code($exception),
                previous: $exception,
                context: $this->context(Operation::Execute, ['query' => $query], $exception),
            );
        }
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function lastInsertId(?string $sequence = null): ?string
    {
        try {
            $id = $this->pdo()->lastInsertId($sequence);
        } catch (PDOException $exception) {
            throw new QueryException(
                message: $exception->getMessage(),
                code: PdoError::code($exception),
                previous: $exception,
                context: $this->context(Operation::LastInsertId, cause: $exception),
            );
        }

        return $id === false ? null : $id;
    }

    /**
     * @throws TransactionException
     */
    public function disconnect(): void
    {
        if ($this->transactions !== null && $this->transactions->inTransaction()) {
            throw new TransactionException(
                'Cannot disconnect while a transaction is active.',
                context: $this->context(Operation::Disconnect),
            );
        }

        $this->transactions = null;
        $this->pdo = null;
    }

    public function name(): string
    {
        return $this->config->name;
    }

    public function driver(): DriverName
    {
        return $this->driver->name();
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private function context(Operation $operation, array $extra = [], ?PDOException $cause = null): array
    {
        return array_merge(
            $this->config->diagnostics(),
            ['operation' => $operation->value],
            $extra,
            PdoError::describe($cause),
        );
    }

    /**
     * @param array<int|string, scalar|null> $parameters
     */
    private function bindParameters(PDOStatement $statement, array $parameters): void
    {
        foreach ($parameters as $key => $value) {
            $statement->bindValue(is_int($key) ? $key + 1 : $key, $value, $this->inferParameterType($value));
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
}
