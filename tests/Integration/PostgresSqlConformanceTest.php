<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Integration;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\PostgresSqlDriver;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

#[Group('conformance')]
#[Group('integration')]
final class PostgresSqlConformanceTest extends DriverConformanceTestCase
{
    protected function driverName(): DriverName
    {
        return DriverName::PostgresSql;
    }

    protected function driver(): Driver
    {
        return new PostgresSqlDriver($this->grammar());
    }

    protected function config(?string $charset = null): ConnectionConfig
    {
        return new ConnectionConfig(
            driver: DriverName::PostgresSql,
            name: 'conformance',
            host: $this->env('DIRTHARA_POSTGRES_HOST', 'postgres'),
            port: (int) $this->env('DIRTHARA_POSTGRES_PORT', '5432'),
            database: $this->env('DIRTHARA_POSTGRES_DATABASE', 'dirthara'),
            username: $this->env('DIRTHARA_POSTGRES_USERNAME', 'dirthara'),
            password: $this->env('DIRTHARA_POSTGRES_PASSWORD', 'dirthara'),
            charset: $charset,
        );
    }

    protected function usersTable(): string
    {
        return 'CREATE TABLE users (id SERIAL PRIMARY KEY, name VARCHAR(255) NOT NULL, active BOOLEAN)';
    }

    /**
     * PostgreSQL reads the last insert id from a sequence, so it has to be named.
     * This is the claim the documentation makes; here it is against a real server.
     */
    protected function sequence(): ?string
    {
        return 'users_id_seq';
    }

    #[Test]
    public function it_leaves_the_client_encoding_alone_without_a_charset(): void
    {
        self::assertSame('UTF8', $this->firstRow('SHOW client_encoding')['client_encoding']);
    }

    #[Test]
    public function it_applies_a_configured_charset_as_the_client_encoding(): void
    {
        $connection = new PdoConnection($this->config(charset: 'LATIN1'), $this->driver());

        $row = $connection->execute('SHOW client_encoding')->first();

        self::assertNotNull($row);
        self::assertSame('LATIN1', $row['client_encoding']);
    }
}
