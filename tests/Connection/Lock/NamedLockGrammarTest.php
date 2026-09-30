<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\Lock;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Lock\NamedLockOutcome;
use Dirthara\Database\Connection\Lock\MySqlNamedLockGrammar;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Lock\SqlServerNamedLockGrammar;
use Dirthara\Database\Connection\Lock\PostgresSqlNamedLockGrammar;

use function strlen;
use function str_repeat;

final class NamedLockGrammarTest extends TestCase
{
    #[Test]
    public function mysql_maps_a_name_to_a_resource_within_its_length_limit(): void
    {
        $grammar = new MySqlNamedLockGrammar();
        $long = str_repeat('x', times: 1000);

        self::assertSame(64, strlen($grammar->resource($this->config(DriverName::MySql), $long)));
        self::assertSame(
            $grammar->resource($this->config(DriverName::MySql), 'alpha'),
            $grammar->resource($this->config(DriverName::MySql), 'alpha'),
        );
        self::assertNotSame(
            $grammar->resource($this->config(DriverName::MySql), $long),
            $grammar->resource($this->config(DriverName::MySql), $long . 'y'),
        );
    }

    #[Test]
    public function mysql_scopes_a_name_to_the_configured_database(): void
    {
        $grammar = new MySqlNamedLockGrammar();

        self::assertNotSame(
            $grammar->resource($this->config(DriverName::MySql, 'app'), 'alpha'),
            $grammar->resource($this->config(DriverName::MySql, 'reporting'), 'alpha'),
        );
        self::assertNotSame(
            $grammar->resource($this->config(DriverName::MySql, 'a'), "b\0c"),
            $grammar->resource($this->config(DriverName::MySql, "a\0b"), 'c'),
        );
    }

    #[Test]
    public function mysql_uses_user_level_locks(): void
    {
        $grammar = new MySqlNamedLockGrammar();

        self::assertSame('SELECT GET_LOCK(?, -1) AS outcome', $grammar->acquire());
        self::assertSame('SELECT GET_LOCK(?, 0) AS outcome', $grammar->tryAcquire());
        self::assertSame('SELECT RELEASE_LOCK(?) AS outcome', $grammar->release());
        self::assertSame(NamedLockOutcome::Granted, $grammar->outcome(1));
        self::assertSame(NamedLockOutcome::Contended, $grammar->outcome(0));
        self::assertSame(NamedLockOutcome::Failed, $grammar->outcome(null));
    }

    #[Test]
    public function postgresql_maps_a_name_to_a_bigint_key(): void
    {
        $grammar = new PostgresSqlNamedLockGrammar();
        $config = $this->config(DriverName::PostgresSql);
        $long = str_repeat('x', times: 1000);

        self::assertSame($grammar->resource($config, 'alpha'), $grammar->resource($config, 'alpha'));
        self::assertNotSame($grammar->resource($config, 'alpha'), $grammar->resource($config, 'beta'));
        self::assertNotSame($grammar->resource($config, $long), $grammar->resource($config, $long . 'y'));
    }

    #[Test]
    public function postgresql_uses_session_level_advisory_locks(): void
    {
        $grammar = new PostgresSqlNamedLockGrammar();

        self::assertSame('SELECT 1 AS outcome FROM pg_advisory_lock(CAST(? AS bigint))', $grammar->acquire());
        self::assertSame(
            'SELECT CASE WHEN pg_try_advisory_lock(CAST(? AS bigint)) THEN 1 ELSE 0 END AS outcome',
            $grammar->tryAcquire(),
        );
        self::assertSame(
            'SELECT CASE WHEN pg_advisory_unlock(CAST(? AS bigint)) THEN 1 ELSE 0 END AS outcome',
            $grammar->release(),
        );
        self::assertSame(NamedLockOutcome::Granted, $grammar->outcome(1));
        self::assertSame(NamedLockOutcome::Contended, $grammar->outcome(0));
        self::assertSame(NamedLockOutcome::Failed, $grammar->outcome(null));
    }

    #[Test]
    public function sql_server_maps_a_name_to_a_resource_it_will_not_truncate(): void
    {
        $grammar = new SqlServerNamedLockGrammar();
        $config = $this->config(DriverName::SqlServer);
        $long = str_repeat('x', times: 1000);

        self::assertLessThanOrEqual(255, strlen($grammar->resource($config, $long)));
        self::assertStringStartsWith('dirthara:', $grammar->resource($config, 'alpha'));
        self::assertNotSame($grammar->resource($config, $long), $grammar->resource($config, $long . 'y'));
    }

    #[Test]
    public function sql_server_uses_session_owned_application_locks(): void
    {
        $grammar = new SqlServerNamedLockGrammar();

        self::assertSame(
            "SET NOCOUNT ON; DECLARE @outcome int; EXEC @outcome = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', "
            . "@LockOwner = 'Session', @LockTimeout = -1; SELECT @outcome AS outcome;",
            $grammar->acquire(),
        );
        self::assertStringContainsString('@LockTimeout = 0;', $grammar->tryAcquire());
        self::assertSame(
            'SET NOCOUNT ON; DECLARE @outcome int; EXEC @outcome = sp_releaseapplock @Resource = ?, @LockOwner = '
            . "'Session'; SELECT @outcome AS outcome;",
            $grammar->release(),
        );
        self::assertSame(NamedLockOutcome::Granted, $grammar->outcome(0));
        self::assertSame(NamedLockOutcome::Granted, $grammar->outcome(1));
        self::assertSame(NamedLockOutcome::Contended, $grammar->outcome(-1));
        self::assertSame(NamedLockOutcome::Failed, $grammar->outcome(-3));
        self::assertSame(NamedLockOutcome::Failed, $grammar->outcome(null));
    }

    private function config(DriverName $driver, ?string $database = 'app'): ConnectionConfig
    {
        return new ConnectionConfig(driver: $driver, database: $database);
    }
}
