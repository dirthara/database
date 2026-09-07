<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Transaction;

use function sprintf;

class SqlServerTransactionGrammar extends PrefixedTransactionGrammar
{
    public function createSavepoint(string $name): string
    {
        return sprintf('SAVE TRANSACTION %s', $name);
    }

    public function releaseSavepoint(string $name): ?string
    {
        return null;
    }

    public function rollbackToSavepoint(string $name): string
    {
        return sprintf('ROLLBACK TRANSACTION %s', $name);
    }
}
