<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

use function trim;

class SqlServerDriver implements Driver
{
    public function name(): DriverName
    {
        return DriverName::SqlServer;
    }

    /**
     * @throws ConnectionException
     */
    public function connect(ConnectionConfig $config): PDO
    {
        $host = $config->host;

        if ($host === null || trim($host) === '') {
            throw new ConnectionException('SQL Server requires a nonempty host.', context: [
                'driver' => $config->driver->value,
            ]);
        }

        $dsn = 'sqlsrv:Server=' . $host;
        $database = $config->database;

        if ($database !== null && trim($database) !== '') {
            $dsn .= ';Database=' . $database;
        }

        return new PDO($dsn, options: $this->options($config));
    }

    private function options(ConnectionConfig $config): array
    {
        return $config->options;
    }
}
