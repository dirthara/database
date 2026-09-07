<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Transaction;

interface TransactionManager
{
    public function begin(): void;

    public function commit(): void;

    public function rollback(): void;

    public function inTransaction(): bool;

    public function level(): int;

    public function run(callable $callback): mixed;
}
