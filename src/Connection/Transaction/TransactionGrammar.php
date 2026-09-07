<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Transaction;

interface TransactionGrammar
{
    public function savepointName(int $level): string;

    public function createSavepoint(string $name): string;

    public function releaseSavepoint(string $name): ?string;

    public function rollbackToSavepoint(string $name): string;
}
