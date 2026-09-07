<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

/**
 * SQLite needs no service, so this is the one conformance run that always happens.
 */
#[Group('conformance')]
final class SQLiteConformanceTest extends DriverConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::SQLite;
    }

    protected function driver(): Driver
    {
        return new SQLiteDriver($this->grammar());
    }

    protected function config(?string $charset = null): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::SQLite,
            name: 'conformance',
            database: ':memory:',
            charset: $charset,
        );
    }

    protected function usersTable(): string
    {
        return 'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER)';
    }
}
