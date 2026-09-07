<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Integration;

use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SqlServerDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;
use Dirthara\Database\Connection\Transaction\SqlServerTransactionGrammar;

/**
 * The only run that exercises SqlServerTransactionGrammar, whose savepoints are
 * spelled SAVE TRANSACTION and cannot be released.
 */
#[Group('conformance')]
#[Group('integration')]
final class SqlServerConformanceTest extends DriverConformanceTestCase
{
    protected function grammar(): TransactionGrammar
    {
        return new SqlServerTransactionGrammar(new SavepointPrefix());
    }

    protected function driverName(): DriverName
    {
        return DriverName::SqlServer;
    }

    protected function driver(): Driver
    {
        return new SqlServerDriver($this->grammar());
    }

    protected function config(?string $charset = null): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::SqlServer,
            name: 'conformance',
            host: $this->env('DIRTHARA_SQLSRV_HOST', 'sqlserver'),
            port: (int) $this->env('DIRTHARA_SQLSRV_PORT', '1433'),
            database: $this->env('DIRTHARA_SQLSRV_DATABASE', 'master'),
            username: $this->env('DIRTHARA_SQLSRV_USERNAME', 'sa'),
            password: $this->env('DIRTHARA_SQLSRV_PASSWORD', 'Dirthara!2026'),
            charset: $charset,
            // ODBC Driver 18 encrypts and verifies by default, and a development
            // server presents a self-signed certificate. Never do this in production.
            dsn: ['TrustServerCertificate' => 'yes'],
        );
    }

    protected function usersTable(): string
    {
        return 'CREATE TABLE users (id INT IDENTITY(1,1) PRIMARY KEY, name NVARCHAR(255) NOT NULL, active BIT)';
    }
}
