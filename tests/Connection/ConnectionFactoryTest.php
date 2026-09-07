<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection;

use Generator;
use ArrayObject;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Connection\ConnectionMiddleware;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

final class ConnectionFactoryTest extends TestCase
{
    private function driver(): SQLiteDriver
    {
        return new SQLiteDriver(new StandardTransactionGrammar(new SavepointPrefix()));
    }

    private function config(): ConnectionConfig
    {
        return new ConnectionConfig(driver: DriverName::SQLite, name: 'primary', database: ':memory:');
    }

    /**
     * @param ArrayObject<int, string> $log
     */
    private function recording(string $label, ArrayObject $log): ConnectionMiddleware
    {
        return new class($label, $log) implements ConnectionMiddleware {
            /**
             * @param ArrayObject<int, string> $log
             */
            public function __construct(
                private readonly string $label,
                private readonly ArrayObject $log,
            ) {}

            public function wrap(Connection $connection): Connection
            {
                $this->log->append($this->label);

                return $connection;
            }
        };
    }

    private function replacing(Connection $replacement): ConnectionMiddleware
    {
        return new class($replacement) implements ConnectionMiddleware {
            public function __construct(
                private readonly Connection $replacement,
            ) {}

            public function wrap(Connection $connection): Connection
            {
                return $this->replacement;
            }
        };
    }

    #[Test]
    public function it_reports_an_unregistered_driver(): void
    {
        try {
            new ConnectionFactory([$this->driver()])->create(new ConnectionConfig(
                driver: DriverName::MySql,
                name: 'reporting',
            ));

            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $exception) {
            self::assertSame('The requested database driver is not registered.', $exception->getMessage());
            self::assertSame('mysql', $exception->getContext()['driver']);
            self::assertSame('reporting', $exception->getContext()['connection']);
        }
    }

    #[Test]
    public function it_rejects_a_duplicated_driver(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('registered more than once');

        new ConnectionFactory([
            $this->driver(),
            new MySqlDriver(new StandardTransactionGrammar(new SavepointPrefix())),
            $this->driver(),
        ]);
    }

    #[Test]
    public function it_returns_a_bare_connection_without_middleware(): void
    {
        self::assertInstanceOf(PdoConnection::class, new ConnectionFactory([$this->driver()])->create($this->config()));
    }

    #[Test]
    public function it_applies_middleware_to_a_created_connection(): void
    {
        $replacement = $this->createStub(Connection::class);

        $factory = new ConnectionFactory([$this->driver()], [$this->replacing($replacement)]);

        self::assertSame($replacement, $factory->create($this->config()));
    }

    #[Test]
    public function the_first_middleware_becomes_the_outermost_layer(): void
    {
        /** @var ArrayObject<int, string> $log */
        $log = new ArrayObject();

        $factory = new ConnectionFactory([$this->driver()], [
            $this->recording('outer', $log),
            $this->recording('inner', $log),
        ]);

        $factory->create($this->config());

        // The innermost layer wraps the bare connection first.
        self::assertSame(['inner', 'outer'], $log->getArrayCopy());
    }

    #[Test]
    public function it_applies_middleware_to_every_connection_it_creates(): void
    {
        $replacement = $this->createStub(Connection::class);

        $factory = new ConnectionFactory($this->drivers(), $this->middleware($replacement));

        self::assertSame($replacement, $factory->create($this->config()));
        self::assertSame($replacement, $factory->create($this->config()));
    }

    /**
     * @return Generator<SQLiteDriver>
     */
    private function drivers(): Generator
    {
        yield $this->driver();
    }

    /**
     * @return Generator<ConnectionMiddleware>
     */
    private function middleware(Connection $replacement): Generator
    {
        yield $this->replacing($replacement);
    }
}
