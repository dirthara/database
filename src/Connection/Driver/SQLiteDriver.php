<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

class SQLiteDriver implements Driver
{
    public function name(): DriverName
    {
        return DriverName::SQLite;
    }

    /**
     * @throws ConnectionException
     */
    public function connect(ConnectionConfig $config): PDO
    {
        $database = $config->database;

        if ($database === null) {
            throw new ConnectionException('SQLite requires an explicit database value.', context: [
                'driver' => $config->driver->value,
            ]);
        }

        return new PDO('sqlite:' . $database, options: $this->options($config));
    }

    private function options(ConnectionConfig $config): array
    {
        return $config->options;
    }
}
