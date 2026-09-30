<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\Group;
use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Database\Query\Sql\LockMode;
use Dirthara\Database\Query\Sql\LockWait;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Database\Exception\UnsupportedLockException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

/**
 * SQLite needs no service, so this is the one conformance run that always happens.
 */
#[Group('conformance')]
final class SQLiteConformanceTest extends TestCase
{
    use DriverConformance;

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

    protected function queryGrammar(): QueryGrammar
    {
        return new SQLiteQueryGrammar();
    }

    protected function usersTable(): string
    {
        return 'CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, active INTEGER)';
    }

    protected function supportsRowLocks(): bool
    {
        return false;
    }

    protected function supportsNamedLocks(): bool
    {
        return false;
    }

    #[Test]
    public function it_refuses_to_acquire_a_named_lock(): void
    {
        $this->expectException(UnsupportedLockException::class);
        $this->expectExceptionMessageIs('SQLite does not support named locks.');

        $this->connection()->locks()->acquire('dirthara:conformance:alpha');
    }

    #[Test]
    public function it_refuses_to_try_for_a_named_lock(): void
    {
        $this->expectException(UnsupportedLockException::class);
        $this->expectExceptionMessageIs('SQLite does not support named locks.');

        $this->connection()->locks()->tryAcquire('dirthara:conformance:alpha');
    }

    /**
     * @return iterable<string, array{LockMode, LockWait}>
     */
    public static function rowLocks(): iterable
    {
        foreach (LockMode::cases() as $mode) {
            foreach (LockWait::cases() as $wait) {
                yield $mode->value . ' ' . $wait->value => [$mode, $wait];
            }
        }
    }

    #[Test]
    #[DataProvider('rowLocks')]
    public function it_refuses_a_row_lock_instead_of_running_the_query_unlocked(LockMode $mode, LockWait $wait): void
    {
        $this->connection()->execute("INSERT INTO users (name, active) VALUES ('Ada', 1)");

        $builder = new QueryBuilder($this->connection(), $this->queryGrammar(), 'users')->lock($mode, $wait);

        $this->connection()->transactions()->begin();

        try {
            $builder->get();

            self::fail('Expected an UnsupportedLockException.');
        } catch (UnsupportedLockException $exception) {
            self::assertSame('SQLite does not support pessimistic row locks.', $exception->getMessage());
        } finally {
            $this->connection()->transactions()->rollback();
        }
    }
}
