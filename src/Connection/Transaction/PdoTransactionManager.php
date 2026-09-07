<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Transaction;

use PDO;
use Throwable;
use PDOException;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Connection\Pdo\PdoError;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\TransactionException;

use function sprintf;
use function array_merge;

final class PdoTransactionManager implements TransactionManager
{
    private int $level = 0;

    public function __construct(
        private readonly PDO $pdo,
        private readonly TransactionGrammar $grammar,
        private readonly ConnectionConfig $config,
    ) {}

    /**
     * @throws TransactionException
     */
    public function begin(): void
    {
        if ($this->level === 0) {
            $this->attempt(static fn(PDO $pdo): bool => $pdo->beginTransaction(), Operation::Begin);
            $this->level = 1;

            return;
        }

        $this->exec($this->grammar->createSavepoint($this->grammar->savepointName($this->level)), Operation::Savepoint);
        $this->level++;
    }

    /**
     * @throws TransactionException
     */
    public function commit(): void
    {
        $this->ensureActiveTransaction(Operation::Commit);

        if ($this->level === 1) {
            $this->attempt(static fn(PDO $pdo): bool => $pdo->commit(), Operation::Commit);
            $this->level = 0;

            return;
        }

        $sql = $this->grammar->releaseSavepoint($this->grammar->savepointName($this->level - 1));

        if ($sql !== null) {
            $this->exec($sql, Operation::ReleaseSavepoint);
        }

        $this->level--;
    }

    /**
     * @throws TransactionException
     */
    public function rollback(): void
    {
        $this->ensureActiveTransaction(Operation::Rollback);

        if ($this->level === 1) {
            $this->attempt(static fn(PDO $pdo): bool => $pdo->rollBack(), Operation::Rollback);
            $this->level = 0;

            return;
        }

        $this->exec(
            $this->grammar->rollbackToSavepoint($this->grammar->savepointName($this->level - 1)),
            Operation::RollbackToSavepoint,
        );

        $this->level--;
    }

    public function inTransaction(): bool
    {
        return $this->level > 0;
    }

    public function level(): int
    {
        return $this->level;
    }

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws Throwable
     */
    public function run(callable $callback): mixed
    {
        $enclosingLevel = $this->level;

        $this->begin();

        try {
            $result = $callback();
        } catch (DatabaseException $exception) {
            throw $exception->addContext($this->abort($enclosingLevel));
        } catch (Throwable $exception) {
            $this->abort($enclosingLevel);

            throw $exception;
        }

        $this->commit();

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function abort(int $enclosingLevel): array
    {
        try {
            $this->rollback();

            return [];
        } catch (Throwable $failure) {
            return ['rollback_failure' => $failure->getMessage()];
        } finally {
            $this->level = $enclosingLevel;
        }
    }

    /**
     * @param callable(PDO): bool $action
     *
     * @throws TransactionException
     */
    private function attempt(callable $action, Operation $operation): void
    {
        try {
            $succeeded = $action($this->pdo);
        } catch (PDOException $exception) {
            throw new TransactionException(
                message: $exception->getMessage(),
                code: PdoError::code($exception),
                previous: $exception,
                context: $this->context($operation, $exception),
            );
        }

        if (!$succeeded) {
            throw new TransactionException(
                sprintf('The database refused the %s operation.', $operation->value),
                context: $this->context($operation),
            );
        }
    }

    /**
     * @throws TransactionException
     */
    private function exec(string $sql, Operation $operation): void
    {
        $this->attempt(static fn(PDO $pdo): bool => $pdo->exec($sql) !== false, $operation);
    }

    /**
     * @throws TransactionException
     */
    private function ensureActiveTransaction(Operation $operation): void
    {
        if (!$this->inTransaction()) {
            throw new TransactionException('There is no active transaction.', context: $this->context($operation));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Operation $operation, ?PDOException $cause = null): array
    {
        return array_merge(
            $this->config->diagnostics(),
            ['operation' => $operation->value],
            PdoError::describe($cause),
        );
    }
}
