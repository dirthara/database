<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

#[Group('conformance')]
#[Group('integration')]
final class MySqlConformanceTest extends DriverConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::MySql;
    }

    protected function driver(): Driver
    {
        return new MySqlDriver($this->grammar());
    }

    protected function config(?string $charset = null): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::MySql,
            name: 'conformance',
            host: $this->env('DIRTHARA_MYSQL_HOST', 'mysql'),
            port: (int) $this->env('DIRTHARA_MYSQL_PORT', '3306'),
            database: $this->env('DIRTHARA_MYSQL_DATABASE', 'dirthara'),
            username: $this->env('DIRTHARA_MYSQL_USERNAME', 'dirthara'),
            password: $this->env('DIRTHARA_MYSQL_PASSWORD', 'dirthara'),
            charset: $charset,
        );
    }

    protected function usersTable(): string
    {
        return 'CREATE TABLE users (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(255) NOT NULL, active TINYINT(1))';
    }

    #[Test]
    public function it_defaults_the_dsn_charset_to_utf8mb4(): void
    {
        self::assertSame('utf8mb4', $this->firstRow('SELECT @@character_set_client AS charset')['charset']);
    }

    #[Test]
    public function it_applies_a_configured_charset_through_the_dsn(): void
    {
        $connection = new PdoConnection($this->config(charset: 'latin1'), $this->driver());

        $row = $connection->execute('SELECT @@character_set_client AS charset')->first();

        self::assertNotNull($row);
        self::assertSame('latin1', $row['charset']);
    }
}
