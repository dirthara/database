<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

class SqlServerDriver extends PdoDriver
{
    public function name(): DriverName
    {
        return DriverName::SqlServer;
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

        return new PDO($dsn, $config->username, $config->password, $this->options($config));
    }
}
