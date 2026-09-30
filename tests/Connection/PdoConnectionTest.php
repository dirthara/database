<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection;

use PDO;
use PDOException;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Exception\QueryException;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Exception\NamedLockException;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Exception\TransactionException;
use Dirthara\Database\Tests\Fixtures\OpensConnections;
use Dirthara\Database\Connection\Lock\NamedLockGrammar;
use Dirthara\Database\Tests\Fixtures\LockingSQLiteDriver;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

use function json_encode;

use const JSON_THROW_ON_ERROR;

final class PdoConnectionTest extends TestCase
{
    use OpensConnections;

    #[Test]
    public function it_binds_a_positional_parameter_list_counted_from_zero(): void
    {
        $connection = $this->withUsers();

        $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

        $row = $connection
            ->execute('SELECT name, active FROM users WHERE name = ? AND active = ?', ['Ada', 1])
            ->first();

        self::assertSame(['name' => 'Ada', 'active' => 1], $row);
    }

    #[Test]
    public function it_binds_named_parameters(): void
    {
        $connection = $this->withUsers();

        $connection->execute('INSERT INTO users (name, active) VALUES (:name, :active)', [
            'name' => 'Grace',
            'active' => 0,
        ]);

        self::assertSame(['Grace'], $connection->execute('SELECT name FROM users')->column('name'));
    }

    #[Test]
    public function it_binds_null_and_boolean_parameters(): void
    {
        $connection = $this->withUsers();

        $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', [null, true]);

        self::assertSame(
            [['name' => null, 'active' => 1]],
            $connection->execute('SELECT name, active FROM users')->all(),
        );
    }

    #[Test]
    public function it_defaults_to_no_parameters(): void
    {
        $connection = $this->withUsers();

        self::assertSame([], $connection->execute('SELECT * FROM users')->all());
    }

    #[Test]
    public function it_translates_a_failing_query_into_a_query_exception(): void
    {
        $connection = $this->sqlite();

        try {
            $connection->execute('SELECT * FROM missing_table');

            self::fail('Expected a QueryException.');
        } catch (QueryException $exception) {
            $context = $exception->context;

            self::assertSame('SELECT * FROM missing_table', $context['query']);
            self::assertSame('execute', $context['operation']);
            self::assertSame('testing', $context['connection']);
            self::assertSame('sqlite', $context['driver']);
            self::assertSame('HY000', $context['sqlstate']);
            self::assertSame(1, $context['driver_code']);
        }
    }

    #[Test]
    public function it_keeps_the_values_a_driver_message_quotes_out_of_the_exception(): void
    {
        $connection = $this->sqlite();
        $connection->execute('CREATE TABLE accounts (email TEXT PRIMARY KEY)');
        $connection->execute('INSERT INTO accounts (email) VALUES (?)', ['ada@example.com']);

        try {
            $connection->execute("INSERT INTO accounts (email) VALUES ('ada@example.com')");

            self::fail('Expected a QueryException.');
        } catch (QueryException $exception) {
            self::assertSame(
                'Unable to execute a query on connection "testing" (SQLSTATE 23000).',
                $exception->getMessage(),
            );
            self::assertStringContainsString('accounts.email', $exception->getPrevious()?->getMessage() ?? '');
            self::assertSame('23000', $exception->context['sqlstate']);
        }
    }

    #[Test]
    public function it_keeps_bound_values_out_of_the_exception_context(): void
    {
        $connection = $this->sqlite();

        try {
            $connection->execute('INSERT INTO missing_table (secret) VALUES (?)', ['hunter2']);

            self::fail('Expected a QueryException.');
        } catch (QueryException $exception) {
            self::assertStringNotContainsString('hunter2', json_encode($exception->context, JSON_THROW_ON_ERROR));
        }
    }

    #[Test]
    public function it_reports_a_silent_prepare_failure_when_the_caller_disables_exception_mode(): void
    {
        $connection = $this->sqlite([PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);

        try {
            $connection->execute('SELECT * FROM missing_table');

            self::fail('Expected a QueryException.');
        } catch (QueryException $exception) {
            self::assertSame(
                'The database refused to prepare a query on connection "testing".',
                $exception->getMessage(),
            );
            self::assertSame(Operation::Prepare->value, $exception->context['operation']);
        }
    }

    #[Test]
    public function it_reports_a_silent_execute_failure_when_the_caller_disables_exception_mode(): void
    {
        $connection = $this->sqlite([PDO::ATTR_ERRMODE => PDO::ERRMODE_SILENT]);

        $connection->execute('CREATE TABLE tags (label TEXT UNIQUE)');
        $connection->execute('INSERT INTO tags (label) VALUES (?)', ['php']);

        try {
            $connection->execute('INSERT INTO tags (label) VALUES (?)', ['php']);

            self::fail('Expected a QueryException.');
        } catch (QueryException $exception) {
            self::assertSame(
                'The database refused to execute a query on connection "testing".',
                $exception->getMessage(),
            );
            self::assertSame('execute', $exception->context['operation']);
        }
    }

    #[Test]
    public function it_applies_caller_supplied_pdo_options(): void
    {
        $connection = $this->sqlite([PDO::ATTR_STRINGIFY_FETCHES => true]);

        self::assertSame([['n' => '1']], $connection->execute('SELECT 1 AS n')->all());
    }

    #[Test]
    public function it_returns_the_last_insert_id(): void
    {
        $connection = $this->withUsers();

        $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

        self::assertSame('1', $connection->lastInsertId());
    }

    #[Test]
    public function it_reports_the_configured_name_and_driver(): void
    {
        $connection = $this->sqlite();

        self::assertSame('testing', $connection->name());
        self::assertSame(DriverName::SQLite, $connection->driver());
    }

    #[Test]
    public function it_reports_transaction_state_without_connecting(): void
    {
        $connection = $this->unreachable();

        self::assertFalse($connection->transactions()->inTransaction());
        self::assertSame(0, $connection->transactions()->level());
    }

    #[Test]
    public function it_connects_only_when_a_transaction_actually_starts(): void
    {
        $this->expectException(ConnectionException::class);

        $this->unreachable()->transactions()->begin();
    }

    #[Test]
    public function it_refuses_to_disconnect_while_a_transaction_is_open(): void
    {
        $connection = $this->withUsers();

        $connection->transactions()->begin();

        try {
            $connection->disconnect();

            self::fail('Expected a TransactionException.');
        } catch (TransactionException $exception) {
            self::assertSame(
                'Unable to disconnect connection "testing" while a transaction is active.',
                $exception->getMessage(),
            );
            self::assertSame('disconnect', $exception->context['operation']);
        }

        self::assertTrue($connection->transactions()->inTransaction());
    }

    #[Test]
    public function it_keeps_one_lock_manager_per_session(): void
    {
        $connection = $this->sqlite();

        self::assertSame($connection->locks(), $connection->locks());
    }

    #[Test]
    public function it_refuses_to_disconnect_while_it_holds_a_named_lock(): void
    {
        $connection = $this->locking();
        $lock = $connection->locks()->acquire('alpha');
        $connection->locks()->acquire('beta');

        try {
            $connection->disconnect();

            self::fail('Expected a NamedLockException.');
        } catch (NamedLockException $exception) {
            self::assertSame(
                'Unable to disconnect connection "locking" while it holds the named locks "alpha", "beta"; release them '
                . 'first.',
                $exception->getMessage(),
            );
            self::assertSame(['alpha', 'beta'], $exception->context['locks']);
            self::assertSame('disconnect', $exception->context['operation']);
        }

        self::assertFalse($lock->released);
        self::assertSame(['alpha', 'beta'], $connection->locks()->held());
    }

    #[Test]
    public function it_disconnects_once_its_named_locks_are_released(): void
    {
        $connection = $this->locking();
        $manager = $connection->locks();

        $manager->acquire('alpha')->release();
        $connection->disconnect();

        self::assertNotSame($manager, $connection->locks());
    }

    #[Test]
    public function it_disconnects_when_no_transaction_is_open(): void
    {
        $connection = $this->withUsers();

        $connection->disconnect();

        self::assertFalse($connection->transactions()->inTransaction());
        self::assertSame([], $connection->execute('SELECT name FROM sqlite_master WHERE name = ?', ['users'])->all());
    }

    #[Test]
    public function it_commits_a_transaction_that_returns(): void
    {
        $connection = $this->withUsers();

        $result = $connection->transactions()->run(static function () use ($connection): string {
            $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertSame(['Ada'], $connection->execute('SELECT name FROM users')->column('name'));
    }

    #[Test]
    public function it_rolls_back_and_rethrows_the_original_exception(): void
    {
        $connection = $this->withUsers();

        $caught = null;

        try {
            $connection->transactions()->run(static function () use ($connection): void {
                $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

                throw new RuntimeException('callback failed');
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertSame('callback failed', $caught->getMessage());

        self::assertSame([], $connection->execute('SELECT name FROM users')->all());
        self::assertFalse($connection->transactions()->inTransaction());
    }

    #[Test]
    public function it_wraps_a_failing_last_insert_id_in_a_query_exception(): void
    {
        $pdo = new class('sqlite::memory:') extends PDO {
            public function lastInsertId(?string $name = null): string|false
            {
                throw new PDOException('the connection is gone');
            }
        };

        $connection = new PdoConnection(
            new ConnectionConfig(driver: DriverName::SQLite, name: 'testing', database: ':memory:'),
            new class($pdo) implements Driver {
                public function __construct(
                    private readonly PDO $pdo,
                ) {}

                public function name(): DriverName
                {
                    return DriverName::SQLite;
                }

                public function transactionGrammar(): TransactionGrammar
                {
                    return new StandardTransactionGrammar(new SavepointPrefix());
                }

                public function namedLockGrammar(): ?NamedLockGrammar
                {
                    return null;
                }

                public function connect(ConnectionConfig $config): PDO
                {
                    return $this->pdo;
                }
            },
        );

        try {
            $connection->lastInsertId();

            self::fail('Expected a QueryException.');
        } catch (QueryException $exception) {
            self::assertSame('Unable to read the last inserted id on connection "testing".', $exception->getMessage());
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
            self::assertSame(Operation::LastInsertId->value, $exception->context['operation']);
            self::assertSame('testing', $exception->context['connection']);
        }
    }

    private function locking(): PdoConnection
    {
        return new PdoConnection(
            new ConnectionConfig(driver: DriverName::SQLite, name: 'locking', database: ':memory:'),
            new LockingSQLiteDriver(),
        );
    }
}
