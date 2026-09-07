<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;

interface Driver
{
    public function name(): DriverName;

    public function transactionGrammar(): TransactionGrammar;

    /**
     * @throws ConnectionException
     */
    public function connect(ConnectionConfig $config): PDO;
}
