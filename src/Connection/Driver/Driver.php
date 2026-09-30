<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Connection\Lock\NamedLockGrammar;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;

interface Driver
{
    public function name(): DriverName;

    public function transactionGrammar(): TransactionGrammar;

    public function namedLockGrammar(): ?NamedLockGrammar;

    /**
     * @throws ConnectionException
     */
    public function connect(ConnectionConfig $config): PDO;
}
