<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ConnectionConfig;

class PostgresSqlDriver implements Driver
{
    public function name(): DriverName
    {
        return DriverName::PostgresSql;
    }

    public function connect(ConnectionConfig $config): PDO
    {
        $dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s', $config->host, $config->port ?? 5432, $config->database);

        return new PDO($dsn, $config->username, $config->password, $this->options($config));
    }

    private function options(ConnectionConfig $config): array
    {
        return array_merge([
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ], $config->options);
    }
}
