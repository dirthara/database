<?php

declare(strict_types=1);


namespace Dirthara\Database\Connection\Transaction;

use PDO;
use Throwable;
use Dirthara\Database\Connection\Exceptions\TransactionException;

final class PdoTransactionManager implements TransactionManager
{
    private int $level = 0;

    public function __construct(
        private readonly PDO $pdo,
        private readonly TransactionGrammar $grammar,
    ) {}

    public function begin(): void
    {
        if ($this->level === 0) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec($this->grammar->createSavepoint($this->savepointName($this->level)));
        }

        $this->level++;
    }

    /**
     * @throws TransactionException
     */
    public function commit(): void
    {
        $this->ensureActiveTransaction();

        $this->level--;

        if ($this->level === 0) {
            $this->pdo->commit();

            return;
        }

        $sql = $this->grammar->releaseSavepoint($this->savepointName($this->level));

        if ($sql !== null) {
            $this->pdo->exec($sql);
        }
    }

    /**
     * @throws TransactionException
     */
    public function rollback(): void
    {
        $this->ensureActiveTransaction();

        $this->level--;

        if ($this->level === 0) {
            $this->pdo->rollBack();

            return;
        }

        $this->pdo->exec($this->grammar->rollbackToSavepoint($this->savepointName($this->level)));
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
     * @throws Throwable
     */
    public function run(callable $callback): mixed
    {
        $this->begin();

        try {
            $result = call_user_func($callback);

            $this->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->rollback();

            throw $exception;
        }
    }

    private function savepointName(int $level): string
    {
        return sprintf('dirthara_%d', $level); // @todo the name dirthara should be configurable
    }

    /**
     * @throws TransactionException
     */
    private function ensureActiveTransaction(): void
    {
        if (!$this->inTransaction()) {
            throw new TransactionException('There is no active transaction.');
        }
    }
}
