<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ConnectionConfig;

class SQLiteDriver implements Driver
{
    public function name(): DriverName
    {
        return DriverName::SQLite;
    }

    public function connect(ConnectionConfig $config): PDO
    {
        return new PDO('sqlite:' . $config->database, options: $this->options($config));
    }

    private function options(ConnectionConfig $config): array
    {
        return $config->options;
    }
}
