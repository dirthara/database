<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

use function sprintf;

class PostgresSqlDriver extends PdoDriver
{
    private const int DEFAULT_PORT = 5432;

    public function name(): DriverName
    {
        return DriverName::PostgresSql;
    }

    /**
     * @throws ConnectionException
     */
    protected function createConnection(ConnectionConfig $config): PDO
    {
        $dsn = sprintf(
            'pgsql:host=%s;port=%d',
            $this->requireHost($config)->value,
            $config->port ?? self::DEFAULT_PORT,
        );

        $database = $this->optionalDatabase($config);

        if ($database !== null) {
            $dsn .= ';dbname=' . $database->value;
        }

        $dsn .= $this->dsnParameters($config);

        $pdo = new PDO($dsn, $config->username, $config->password, $this->options($config));

        $charset = $this->charset($config);

        if ($charset !== null) {
            $pdo->exec(sprintf("SET client_encoding TO '%s'", $charset->value));
        }

        return $pdo;
    }
}
