<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

final class ConnectionManagerTest extends TestCase
{
    private function factory(): ConnectionFactory
    {
        return new ConnectionFactory([new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()))]);
    }

    private function config(string $name): ConnectionConfig
    {
        return new ConnectionConfig(driver: DriverName::SQLite, name: $name, database: ':memory:');
    }

    #[Test]
    public function it_resolves_the_default_connection(): void
    {
        $manager = new ConnectionManager($this->factory(), [$this->config('default')]);

        self::assertSame('default', $manager->connection()->name());
    }

    #[Test]
    public function it_caches_a_resolved_connection(): void
    {
        $manager = new ConnectionManager($this->factory(), [$this->config('default')]);

        self::assertSame($manager->connection(), $manager->connection('default'));
    }

    #[Test]
    public function it_reports_an_unconfigured_connection(): void
    {
        $manager = new ConnectionManager($this->factory(), [$this->config('primary')]);

        try {
            $manager->connection('replica');

            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $exception) {
            self::assertSame('The requested database connection is not configured.', $exception->getMessage());
            self::assertSame('replica', $exception->getContext()['connection']);
            self::assertSame(['primary'], $exception->getContext()['configured']);
        }
    }

    #[Test]
    public function it_reports_a_missing_default_connection(): void
    {
        $manager = new ConnectionManager($this->factory(), [$this->config('primary')]);

        $this->expectException(ConnectionException::class);

        $manager->connection();
    }

    #[Test]
    public function it_rejects_a_duplicated_connection_name(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('configured more than once');

        new ConnectionManager($this->factory(), [$this->config('primary'), $this->config('primary')]);
    }

    #[Test]
    public function it_lists_the_configured_names(): void
    {
        $manager = new ConnectionManager($this->factory(), [$this->config('primary'), $this->config('replica')]);

        self::assertSame(['primary', 'replica'], $manager->names());
    }

    #[Test]
    public function it_discards_a_disconnected_connection(): void
    {
        $manager = new ConnectionManager($this->factory(), [$this->config('default')]);

        $first = $manager->connection();
        $manager->disconnect();

        self::assertNotSame($first, $manager->connection());
    }

    #[Test]
    public function it_disconnects_every_connection(): void
    {
        $manager = new ConnectionManager($this->factory(), [$this->config('primary'), $this->config('replica')]);

        $primary = $manager->connection('primary');
        $manager->connection('replica');

        $manager->disconnectAll();

        self::assertNotSame($primary, $manager->connection('primary'));
    }

    #[Test]
    public function disconnecting_an_unresolved_connection_does_nothing(): void
    {
        $manager = new ConnectionManager($this->factory(), [$this->config('default')]);

        $manager->disconnect();

        self::assertSame('default', $manager->connection()->name());
    }
}
