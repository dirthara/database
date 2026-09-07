<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\Transaction;

use PDO;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Exceptions\DatabaseException;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\TransactionException;
use Dirthara\Database\Connection\Transaction\PdoTransactionManager;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

final class PdoTransactionManagerTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $this->pdo->exec('CREATE TABLE users (name TEXT)');
    }

    private function manager(): PdoTransactionManager
    {
        return new PdoTransactionManager(
            fn(): PDO => $this->pdo,
            new StandardTransactionGrammar(new SavepointPrefix()),
            new ConnectionConfig(driver: DriverName::SQLite, name: 'testing', database: ':memory:'),
        );
    }

    /**
     * @return list<string>
     */
    private function names(): array
    {
        $statement = $this->pdo->query('SELECT name FROM users');

        self::assertNotFalse($statement);

        /** @var list<string> $names */
        // @mago-expect lint:inline-variable-return
        $names = $statement->fetchAll(PDO::FETCH_COLUMN);

        return $names;
    }

    #[Test]
    public function it_tracks_the_nesting_level(): void
    {
        $manager = $this->manager();

        self::assertSame(0, $manager->level());
        self::assertFalse($manager->inTransaction());

        $manager->begin();
        self::assertSame(1, $manager->level());

        $manager->begin();
        self::assertSame(2, $manager->level());
        self::assertTrue($manager->inTransaction());

        $manager->commit();
        self::assertSame(1, $manager->level());

        $manager->commit();
        self::assertSame(0, $manager->level());
        self::assertFalse($manager->inTransaction());
    }

    #[Test]
    public function it_rolls_back_a_nested_savepoint_without_ending_the_outer_transaction(): void
    {
        $manager = $this->manager();

        $manager->begin();
        $this->pdo->exec("INSERT INTO users (name) VALUES ('outer')");

        $manager->begin();
        $this->pdo->exec("INSERT INTO users (name) VALUES ('inner')");
        $manager->rollback();

        self::assertSame(1, $manager->level());
        self::assertSame(['outer'], $this->names());

        $manager->commit();

        self::assertSame(['outer'], $this->names());
    }

    #[Test]
    public function it_rejects_a_commit_without_an_active_transaction(): void
    {
        try {
            $this->manager()->commit();

            self::fail('Expected a TransactionException.');
        } catch (TransactionException $exception) {
            self::assertSame('There is no active transaction.', $exception->getMessage());
            self::assertSame('commit', $exception->getContext()['operation']);
            self::assertSame('testing', $exception->getContext()['connection']);
        }
    }

    #[Test]
    public function it_rejects_a_rollback_without_an_active_transaction(): void
    {
        $this->expectException(TransactionException::class);

        $this->manager()->rollback();
    }

    #[Test]
    public function it_rethrows_the_callback_exception_after_rolling_back(): void
    {
        $manager = $this->manager();

        $caught = null;

        try {
            $manager->run(function (): void {
                $this->pdo->exec("INSERT INTO users (name) VALUES ('ada')");

                throw new RuntimeException('callback failed');
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertSame('callback failed', $caught->getMessage());

        self::assertSame([], $this->names());
        self::assertSame(0, $manager->level());
    }

    #[Test]
    public function it_preserves_the_original_exception_when_the_rollback_also_fails(): void
    {
        $manager = $this->manager();

        $caught = null;

        try {
            $manager->run(function (): void {
                // Ending the transaction behind the manager's back makes its
                // own rollback fail as well.
                $this->pdo->commit();

                throw new DatabaseException('callback failed');
            });
        } catch (DatabaseException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(DatabaseException::class, $caught);
        self::assertSame('callback failed', $caught->getMessage());
        self::assertArrayHasKey('rollback_failure', $caught->getContext());

        self::assertSame(0, $manager->level());
    }

    #[Test]
    public function a_failed_nested_rollback_restores_the_enclosing_nesting_level(): void
    {
        $manager = $this->manager();

        $manager->begin();

        $caught = null;

        try {
            $manager->run(function (): void {
                $this->pdo->commit();

                throw new DatabaseException('inner failed');
            });
        } catch (DatabaseException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(DatabaseException::class, $caught);
        self::assertArrayHasKey('rollback_failure', $caught->getContext());
        self::assertSame(1, $manager->level());
    }

    #[Test]
    public function it_returns_the_callback_result(): void
    {
        self::assertSame('value', $this->manager()->run(static fn(): string => 'value'));
    }

    #[Test]
    public function it_nests_run_calls(): void
    {
        $manager = $this->manager();

        $manager->run(function () use ($manager): void {
            $this->pdo->exec("INSERT INTO users (name) VALUES ('outer')");

            try {
                $manager->run(function (): void {
                    $this->pdo->exec("INSERT INTO users (name) VALUES ('inner')");

                    throw new RuntimeException('inner failed');
                });
            } catch (RuntimeException $exception) {
                self::assertSame('inner failed', $exception->getMessage());
            }
        });

        self::assertSame(['outer'], $this->names());
        self::assertSame(0, $manager->level());
    }

    #[Test]
    public function it_reports_an_operation_the_database_refused(): void
    {
        $this->pdo = new class('sqlite::memory:') extends PDO {
            public function beginTransaction(): bool
            {
                return false;
            }
        };

        try {
            $this->manager()->begin();

            self::fail('Expected a TransactionException.');
        } catch (TransactionException $exception) {
            self::assertSame('The database refused the begin operation.', $exception->getMessage());
            self::assertSame(Operation::Begin->value, $exception->getContext()['operation']);
        }
    }
}
