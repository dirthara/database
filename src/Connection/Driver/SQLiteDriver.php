<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Exception\InvalidConnectionConfigException;

final class SQLiteDriver extends PdoDriver
{
    public function name(): DriverName
    {
        return DriverName::SQLite;
    }

    /**
     * @throws ConnectionException
     */
    protected function createConnection(ConnectionConfig $config): PDO
    {
        $this->rejectCharset($config);
        $this->rejectDsnParameters($config);

        $database = $config->database;

        if ($database === null) {
            throw InvalidConnectionConfigException::missingDatabase($config);
        }

        return new PDO('sqlite:' . $database, options: $this->options($config));
    }
}
