<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

class SQLiteDriver extends PdoDriver
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

        $database = $config->database;

        if ($database === null) {
            throw new ConnectionException(
                'SQLite requires an explicit database value.',
                context: $this->context($config),
            );
        }

        return new PDO('sqlite:' . $database, options: $this->options($config));
    }
}
