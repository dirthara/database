<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Transaction;

use Throwable;
use Dirthara\Database\Connection\Exceptions\TransactionException;

interface TransactionManager
{
    /**
     * @throws TransactionException
     */
    public function begin(): void;

    /**
     * @throws TransactionException
     */
    public function commit(): void;

    /**
     * @throws TransactionException
     */
    public function rollback(): void;

    public function inTransaction(): bool;

    public function level(): int;

    /**
     * @template T
     *
     * @param callable(): T $callback
     *
     * @return T
     *
     * @throws Throwable
     */
    public function run(callable $callback): mixed;
}
