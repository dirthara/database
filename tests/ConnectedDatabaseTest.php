<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests;

use RuntimeException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Tests\Query\Doubles\RecordingGrammar;

final class ConnectedDatabaseTest extends ConnectionTestCase
{
    private RecordingGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new RecordingGrammar();
    }

    private function database(?Connection $connection = null): ConnectedDatabase
    {
        return new ConnectedDatabase($connection ?? $this->sqlite(), $this->grammar);
    }

    #[Test]
    public function it_exposes_the_connection_it_was_given(): void
    {
        $connection = $this->sqlite();

        self::assertSame($connection, $this->database($connection)->connection());
    }

    #[Test]
    public function it_builds_a_query_for_a_table(): void
    {
        self::assertSame('users', $this->database()->table('users')->toSelectQuery()->table);
    }

    #[Test]
    public function it_builds_a_query_with_the_grammar_it_was_given(): void
    {
        $builder = $this->database()->table('users');

        self::assertSame($this->grammar->result, $builder->compile());
        self::assertNotNull($this->grammar->select);
        self::assertSame('users', $this->grammar->select->table);
    }

    #[Test]
    public function it_builds_a_query_over_the_connection_it_was_given(): void
    {
        $connection = $this->withUsers();
        $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

        $this->grammar->result = new CompiledQuery('SELECT name FROM users');

        self::assertSame([['name' => 'Ada']], $this->database($connection)->table('users')->get());
    }

    #[Test]
    public function it_rejects_an_empty_table(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A query table cannot be empty.');

        $this->database()->table('');
    }

    #[Test]
    public function it_executes_a_query(): void
    {
        $database = $this->database($this->withUsers());

        $database->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

        self::assertSame([['name' => 'Ada']], $database->execute('SELECT name FROM users')->all());
    }

    #[Test]
    public function it_reports_a_failing_query(): void
    {
        $this->expectException(QueryException::class);

        $this->database()->execute('SELECT * FROM missing');
    }

    #[Test]
    public function it_returns_the_result_of_a_transaction(): void
    {
        self::assertSame('done', $this->database()->transaction(static fn(): string => 'done'));
    }

    #[Test]
    public function it_passes_itself_to_the_transaction_callback(): void
    {
        $database = $this->database();

        self::assertSame($database, $database->transaction(static fn(ConnectedDatabase $scope) => $scope));
    }

    #[Test]
    public function it_commits_a_transaction(): void
    {
        $database = $this->database($this->withUsers());

        $database->transaction(static function (ConnectedDatabase $scope): void {
            $scope->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);
        });

        self::assertSame([['name' => 'Ada']], $database->execute('SELECT name FROM users')->all());
    }

    #[Test]
    public function it_rolls_back_a_failed_transaction(): void
    {
        $database = $this->database($this->withUsers());

        try {
            $database->transaction(static function (ConnectedDatabase $scope): void {
                $scope->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

                throw new RuntimeException('The callback failed.');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('The callback failed.', $exception->getMessage());
        }

        self::assertSame([], $database->execute('SELECT name FROM users')->all());
    }

    #[Test]
    public function it_nests_transactions(): void
    {
        $database = $this->database($this->withUsers());

        $database->transaction(static function (ConnectedDatabase $outer): void {
            $outer->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

            $outer->transaction(static function (ConnectedDatabase $inner): void {
                $inner->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Grace', 0]);
            });
        });

        self::assertSame(
            [['name' => 'Ada'], ['name' => 'Grace']],
            $database->execute('SELECT name FROM users ORDER BY id')->all(),
        );
    }

    #[Test]
    public function it_rolls_back_a_failed_nested_transaction(): void
    {
        $database = $this->database($this->withUsers());

        $database->transaction(static function (ConnectedDatabase $outer): void {
            $outer->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

            try {
                $outer->transaction(static function (ConnectedDatabase $inner): void {
                    $inner->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Grace', 0]);

                    throw new RuntimeException('The inner callback failed.');
                });
            } catch (RuntimeException $exception) {
                self::assertSame('The inner callback failed.', $exception->getMessage());
            }
        });

        self::assertSame([['name' => 'Ada']], $database->execute('SELECT name FROM users')->all());
    }

    #[Test]
    public function it_builds_queries_inside_the_transaction(): void
    {
        $database = $this->database($this->withUsers());

        $this->grammar->result = new CompiledQuery('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);

        try {
            $database->transaction(static function (ConnectedDatabase $scope): void {
                $scope->table('users')->insert(['name' => 'Ada', 'active' => 1]);

                throw new RuntimeException('The callback failed.');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('The callback failed.', $exception->getMessage());
        }

        self::assertSame([], $database->execute('SELECT name FROM users')->all());
    }
}
