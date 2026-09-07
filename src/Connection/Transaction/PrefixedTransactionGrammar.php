<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Transaction;

use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;

abstract class PrefixedTransactionGrammar implements TransactionGrammar
{
    public function __construct(
        private readonly SavepointPrefix $prefix,
    ) {}

    public function savepointName(int $level): string
    {
        return $this->prefix->value . $level;
    }
}
