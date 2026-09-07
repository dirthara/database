<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests;

use PHPUnit\Framework\TestCase;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

abstract class ConnectionTestCase extends TestCase
{
    /**
     * @param array<int, mixed> $options
     */
    protected function sqlite(array $options = []): Connection
    {
        return new PdoConnection(
            new ConnectionConfig(driver: DriverName::SQLite, name: 'testing', database: ':memory:', options: $options),
            new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix())),
        );
    }

    /**
     * A connection whose database cannot be opened, so any attempt to connect fails.
     */
    protected function unreachable(): Connection
    {
        return new PdoConnection(
            new ConnectionConfig(
                driver: DriverName::SQLite,
                name: 'unreachable',
                database: '/nonexistent/dirthara.sqlite',
            ),
            new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix())),
        );
    }

    protected function withUsers(): Connection
    {
        $connection = $this->sqlite();

        $connection->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, active INTEGER)');

        return $connection;
    }
}
