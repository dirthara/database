<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection;

use PDO;
use RuntimeException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Tests\ConnectionTestCase;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Connection\Exceptions\TransactionException;

final class PdoConnectionTest extends ConnectionTestCase
{
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
            $context = $exception->getContext();

            self::assertSame('SELECT * FROM missing_table', $context['query']);
            self::assertSame('execute', $context['operation']);
            self::assertSame('testing', $context['connection']);
            self::assertSame('sqlite', $context['driver']);
            self::assertSame('HY000', $context['sqlstate']);
            self::assertSame(1, $context['driver_code']);
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
            self::assertStringNotContainsString('hunter2', json_encode($exception->getContext(), JSON_THROW_ON_ERROR));
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
            self::assertSame('Failed to prepare the query.', $exception->getMessage());
            self::assertSame(Operation::Prepare->value, $exception->getContext()['operation']);
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
            self::assertSame('Failed to execute the query.', $exception->getMessage());
            self::assertSame('execute', $exception->getContext()['operation']);
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
    public function it_refuses_to_disconnect_while_a_transaction_is_open(): void
    {
        $connection = $this->withUsers();

        $connection->beginTransaction();

        try {
            $connection->disconnect();

            self::fail('Expected a TransactionException.');
        } catch (TransactionException $exception) {
            self::assertSame('Cannot disconnect while a transaction is active.', $exception->getMessage());
            self::assertSame('disconnect', $exception->getContext()['operation']);
        }

        self::assertTrue($connection->inTransaction());
    }

    #[Test]
    public function it_disconnects_when_no_transaction_is_open(): void
    {
        $connection = $this->withUsers();

        $connection->disconnect();

        self::assertFalse($connection->inTransaction());
        self::assertSame([], $connection->execute('SELECT name FROM sqlite_master WHERE name = ?', ['users'])->all());
    }

    #[Test]
    public function it_commits_a_transaction_that_returns(): void
    {
        $connection = $this->withUsers();

        $result = $connection->transaction(static function ($transactional): string {
            $transactional->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

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
            $connection->transaction(static function ($transactional): void {
                $transactional->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

                throw new RuntimeException('callback failed');
            });
        } catch (RuntimeException $exception) {
            $caught = $exception;
        }

        self::assertInstanceOf(RuntimeException::class, $caught);
        self::assertSame('callback failed', $caught->getMessage());

        self::assertSame([], $connection->execute('SELECT name FROM users')->all());
        self::assertFalse($connection->inTransaction());
    }
}
