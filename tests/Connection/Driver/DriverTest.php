<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\Driver;

use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Connection\Driver\SqlServerDriver;
use Dirthara\Database\Connection\Driver\PostgresSqlDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;
use Dirthara\Database\Connection\Transaction\SqlServerTransactionGrammar;

final class DriverTest extends TestCase
{
    /**
     * @return array<string, array{Driver, DriverName}>
     */
    public static function hostDrivers(): array
    {
        return [
            'mysql' => [new MySqlDriver(new StandardTransactionGrammar(new SavepointPrefix())), DriverName::MySql],
            'pgsql' => [
                new PostgresSqlDriver(new StandardTransactionGrammar(new SavepointPrefix())),
                DriverName::PostgresSql,
            ],
            'sqlsrv' => [
                new SqlServerDriver(new SqlServerTransactionGrammar(new SavepointPrefix())),
                DriverName::SqlServer,
            ],
        ];
    }

    #[Test]
    #[DataProvider('hostDrivers')]
    public function it_requires_a_nonempty_host(Driver $driver, DriverName $name): void
    {
        try {
            $driver->connect(new ConnectionConfig(driver: $name, name: 'primary', host: '   '));

            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $exception) {
            self::assertStringContainsString('requires a nonempty host', $exception->getMessage());
            self::assertSame('primary', $exception->getContext()['connection']);
            self::assertSame($name->value, $exception->getContext()['driver']);
        }
    }

    #[Test]
    #[DataProvider('hostDrivers')]
    public function it_rejects_a_host_that_would_inject_dsn_parameters(Driver $driver, DriverName $name): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('must not contain a semicolon');

        $driver->connect(new ConnectionConfig(driver: $name, host: 'localhost;dbname=other'));
    }

    #[Test]
    #[DataProvider('hostDrivers')]
    public function it_rejects_a_database_that_would_inject_dsn_parameters(Driver $driver, DriverName $name): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('must not contain a semicolon');

        $driver->connect(new ConnectionConfig(driver: $name, host: 'localhost', database: 'app;Trusted=yes'));
    }

    #[Test]
    public function it_rejects_a_charset_that_is_not_an_identifier(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('not a valid identifier');

        new MySqlDriver(new StandardTransactionGrammar(new SavepointPrefix()))->connect(new ConnectionConfig(
            driver: DriverName::MySql,
            host: 'localhost',
            charset: "utf8'; DROP TABLE users; --",
        ));
    }

    #[Test]
    public function sqlite_requires_a_database(): void
    {
        try {
            new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))->connect(new ConnectionConfig(
                driver: DriverName::SQLite,
                name: 'cache',
            ));

            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $exception) {
            self::assertSame('SQLite requires an explicit database value.', $exception->getMessage());
            self::assertSame('cache', $exception->getContext()['connection']);
        }
    }

    #[Test]
    public function sqlite_rejects_a_charset_it_cannot_honour(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('SQLite does not support a configurable charset.');

        new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))->connect(new ConnectionConfig(
            driver: DriverName::SQLite,
            database: ':memory:',
            charset: 'utf8mb4',
        ));
    }

    #[Test]
    public function sql_server_rejects_a_charset_it_cannot_honour(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('SqlServer does not support a configurable charset.');

        new SqlServerDriver(new SqlServerTransactionGrammar(new SavepointPrefix()))->connect(new ConnectionConfig(
            driver: DriverName::SqlServer,
            host: 'localhost',
            charset: 'utf8',
        ));
    }

    #[Test]
    public function sqlite_applies_the_shared_pdo_defaults(): void
    {
        $pdo = new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))->connect(new ConnectionConfig(
            driver: DriverName::SQLite,
            database: ':memory:',
        ));

        self::assertSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    #[Test]
    public function a_caller_option_overrides_a_shared_default(): void
    {
        $pdo = new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))->connect(new ConnectionConfig(
            driver: DriverName::SQLite,
            database: ':memory:',
            options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING],
        ));

        self::assertSame(PDO::ERRMODE_WARNING, $pdo->getAttribute(PDO::ATTR_ERRMODE));
    }

    #[Test]
    public function each_driver_reports_its_name(): void
    {
        self::assertSame(
            DriverName::MySql,
            new MySqlDriver(new StandardTransactionGrammar(new SavepointPrefix()))->name(),
        );
        self::assertSame(
            DriverName::PostgresSql,
            new PostgresSqlDriver(new StandardTransactionGrammar(new SavepointPrefix()))->name(),
        );
        self::assertSame(
            DriverName::SqlServer,
            new SqlServerDriver(new SqlServerTransactionGrammar(new SavepointPrefix()))->name(),
        );
        self::assertSame(
            DriverName::SQLite,
            new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))->name(),
        );
    }

    #[Test]
    public function a_driver_exposes_the_grammar_it_was_given(): void
    {
        $standard = new StandardTransactionGrammar(new SavepointPrefix('custom'));
        $sqlServer = new SqlServerTransactionGrammar(new SavepointPrefix());

        self::assertSame($standard, new MySqlDriver($standard)->transactionGrammar());
        self::assertSame($sqlServer, new SqlServerDriver($sqlServer)->transactionGrammar());
    }
}
