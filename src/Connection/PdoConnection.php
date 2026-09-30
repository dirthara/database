<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use PDO;
use PDOException;
use PDOStatement;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Exception\QueryException;
use Dirthara\Database\Connection\Lock\LockManager;
use Dirthara\Database\Connection\Result\PdoResult;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Exception\NamedLockException;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Connection\Lock\PdoLockManager;
use Dirthara\Database\Exception\TransactionException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionManager;
use Dirthara\Database\Connection\Transaction\PdoTransactionManager;

use function is_int;
use function is_bool;
use function is_null;

final class PdoConnection implements Connection
{
    private ?PDO $pdo = null;
    private ?TransactionManager $transactions = null;

    private ?LockManager $locks = null;

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

    public function locks(): LockManager
    {
        return $this->locks ??= new PdoLockManager($this->pdo(...), $this->driver->namedLockGrammar(), $this->config);
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
                throw QueryException::prepareRefused($this->config, $query);
            }

            $this->bindParameters($statement, $parameters);

            if ($statement->execute() === false) {
                throw QueryException::executeRefused($this->config, $query);
            }

            return new PdoResult($statement);
        } catch (PDOException $exception) {
            throw QueryException::executeFailed($this->config, $query, $exception);
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
            throw QueryException::lastInsertIdFailed($this->config, $exception);
        }

        return $id === false ? null : $id;
    }

    /**
     * @throws TransactionException
     * @throws NamedLockException
     */
    public function disconnect(): void
    {
        if ($this->transactions !== null && $this->transactions->inTransaction()) {
            throw TransactionException::activeOnDisconnect($this->config);
        }

        $held = $this->locks === null ? [] : $this->locks->held();

        if ($held !== []) {
            throw NamedLockException::heldOnDisconnect($this->config, $held);
        }

        $this->transactions = null;
        $this->locks = null;
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
