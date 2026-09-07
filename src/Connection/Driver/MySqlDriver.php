<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

use function sprintf;
use function array_replace;

class MySqlDriver extends PdoDriver
{
    private const int DEFAULT_PORT = 3306;

    private const string DEFAULT_CHARSET = 'utf8mb4';

    public function name(): DriverName
    {
        return DriverName::MySql;
    }

    /**
     * @throws ConnectionException
     */
    protected function createConnection(ConnectionConfig $config): PDO
    {
        $charset = $this->charset($config);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;charset=%s',
            $this->requireHost($config)->value,
            $config->port ?? self::DEFAULT_PORT,
            $charset === null ? self::DEFAULT_CHARSET : $charset->value,
        );

        $database = $this->optionalDatabase($config);

        if ($database !== null) {
            $dsn .= ';dbname=' . $database->value;
        }

        return new PDO($dsn, $config->username, $config->password, $this->options($config));
    }

    /**
     * @return array<int, mixed>
     */
    protected function options(ConnectionConfig $config): array
    {
        return array_replace([PDO::ATTR_EMULATE_PREPARES => false], parent::options($config));
    }
}
