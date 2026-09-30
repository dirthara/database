<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Transaction;

use function sprintf;

final class StandardTransactionGrammar extends PrefixedTransactionGrammar
{
    public function createSavepoint(string $name): string
    {
        return sprintf('SAVEPOINT %s', $name);
    }

    public function releaseSavepoint(string $name): ?string
    {
        return sprintf('RELEASE SAVEPOINT %s', $name);
    }

    public function rollbackToSavepoint(string $name): string
    {
        return sprintf('ROLLBACK TO SAVEPOINT %s', $name);
    }
}
