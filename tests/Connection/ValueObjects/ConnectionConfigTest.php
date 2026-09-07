<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\ValueObjects;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

final class ConnectionConfigTest extends TestCase
{
    private function config(): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::MySql,
            name: 'primary',
            host: 'db.example.com',
            port: 3307,
            database: 'app',
            username: 'app_user',
            // @mago-expect lint:no-literal-password
            password: 'hunter2',
            charset: 'utf8mb4',
        );
    }

    #[Test]
    public function it_exposes_diagnostics_without_credentials(): void
    {
        self::assertSame(
            [
                'connection' => 'primary',
                'driver' => 'mysql',
                'host' => 'db.example.com',
                'port' => 3307,
                'database' => 'app',
            ],
            $this->config()->diagnostics(),
        );
    }

    #[Test]
    public function it_omits_absent_values_from_diagnostics(): void
    {
        $config = new ConnectionConfig(driver: DriverName::SQLite, database: ':memory:');

        self::assertSame(
            [
                'connection' => 'default',
                'driver' => 'sqlite',
                'database' => ':memory:',
            ],
            $config->diagnostics(),
        );
    }

    #[Test]
    public function it_redacts_the_password_in_debug_output(): void
    {
        $dumped = print_r($this->config(), return: true);

        self::assertStringNotContainsString('hunter2', $dumped);
        self::assertStringContainsString('[redacted]', $dumped);
    }

    #[Test]
    public function it_reports_a_null_password_as_null(): void
    {
        $config = new ConnectionConfig(driver: DriverName::SQLite, database: ':memory:');

        self::assertNull($config->__debugInfo()['password']);
    }

    #[Test]
    public function it_defaults_the_name_to_default(): void
    {
        self::assertSame('default', new ConnectionConfig(driver: DriverName::SQLite)->name);
    }
}
