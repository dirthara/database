<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

use function trim;

class PostgresSqlDriver implements Driver
{
    public function name(): DriverName
    {
        return DriverName::PostgresSql;
    }

    /**
     * @throws ConnectionException
     */
    public function connect(ConnectionConfig $config): PDO
    {
        $host = $config->host;

        if ($host === null || trim($host) === '') {
            throw new ConnectionException('PostgreSQL requires a nonempty host.', context: [
                'driver' => $config->driver->value,
            ]);
        }

        $dsn = sprintf('pgsql:host=%s;port=%d', $host, $config->port ?? 5432);
        $database = $config->database;

        if ($database !== null && trim($database) !== '') {
            $dsn .= ';dbname=' . $database;
        }

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
