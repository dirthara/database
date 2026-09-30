<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Lock\SqlServerNamedLockGrammar;

final class SqlServerDriver extends PdoDriver
{
    public function name(): DriverName
    {
        return DriverName::SqlServer;
    }

    public function namedLockGrammar(): SqlServerNamedLockGrammar
    {
        return new SqlServerNamedLockGrammar();
    }

    /**
     * @throws ConnectionException
     */
    protected function createConnection(ConnectionConfig $config): PDO
    {
        $this->rejectCharset($config);

        $server = $this->requireHost($config)->value;

        if ($config->port !== null) {
            $server .= ',' . $config->port;
        }

        $dsn = 'sqlsrv:Server=' . $server;

        $database = $this->optionalDatabase($config);

        if ($database !== null) {
            $dsn .= ';Database=' . $database->value;
        }

        $dsn .= $this->dsnParameters($config);

        return new PDO($dsn, $config->username, $config->password, $this->options($config));
    }
}
