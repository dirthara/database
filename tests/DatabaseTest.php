<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests;

use RuntimeException;
use InvalidArgumentException;
use Dirthara\Database\Database;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\ConnectedDatabase;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Connection\ConnectionFactory;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Driver\MySqlDriver;
use Dirthara\Database\Connection\Driver\SQLiteDriver;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Tests\Query\Doubles\RecordingGrammar;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

final class DatabaseTest extends TestCase
{
    private RecordingGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new RecordingGrammar();
    }

    private function config(string $name, DriverName $driver = DriverName::SQLite): ConnectionConfig
    {
        return new ConnectionConfig(driver: $driver, name: $name, database: ':memory:');
    }

    /**
     * @throws ConnectionException
     */
    private function manager(ConnectionConfig ...$configs): ConnectionManager
    {
        $transactions = new StandardTransactionGrammar(new SavepointPrefix());

        return new ConnectionManager(
            new ConnectionFactory([new SQLiteDriver($transactions), new MySqlDriver($transactions)]),
            $configs === [] ? [$this->config('default')] : $configs,
        );
    }

    private function resolver(): QueryGrammarResolver
    {
        return new QueryGrammarResolver([DriverName::SQLite->value => $this->grammar]);
    }

    /**
     * @throws ConnectionException
     */
    private function database(?ConnectionManager $connections = null, ?QueryGrammarResolver $grammars = null): Database
    {
        return new Database($connections ?? $this->manager(), $grammars ?? $this->resolver());
    }

    #[Test]
    public function it_resolves_the_default_connection(): void
    {
        self::assertSame('default', $this->database()->connection()->name());
    }

    #[Test]
    public function it_resolves_a_named_connection(): void
    {
        $database = $this->database($this->manager($this->config('default'), $this->config('reporting')));

        self::assertSame('reporting', $database->connection('reporting')->name());
    }

    #[Test]
    public function it_reports_an_unconfigured_connection(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('The requested database connection is not configured.');

        $this->database()->connection('missing');
    }

    #[Test]
    public function it_scopes_itself_to_the_default_connection(): void
    {
        $database = $this->database();

        self::assertSame($database->connection(), $database->using()->connection());
    }

    #[Test]
    public function it_scopes_itself_to_a_named_connection(): void
    {
        $database = $this->database($this->manager($this->config('default'), $this->config('reporting')));

        self::assertSame('reporting', $database->using('reporting')->connection()->name());
    }

    #[Test]
    public function it_scopes_itself_with_the_grammar_for_the_connection_driver(): void
    {
        $mysql = new RecordingGrammar();

        $database = new Database(
            $this->manager($this->config('default'), $this->config('reporting', DriverName::MySql)),
            new QueryGrammarResolver([
                DriverName::SQLite->value => $this->grammar,
                DriverName::MySql->value => $mysql,
            ]),
        );

        $database->using('reporting')->table('orders')->compile();

        self::assertNull($this->grammar->select);
        self::assertNotNull($mysql->select);
        self::assertSame('orders', $mysql->select->table);
    }

    #[Test]
    public function it_reports_an_unconfigured_connection_when_scoping(): void
    {
        $this->expectException(ConnectionException::class);

        $this->database()->using('missing');
    }

    #[Test]
    public function it_reports_a_driver_without_a_grammar(): void
    {
        $database = $this->database(grammars: new QueryGrammarResolver());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No query grammar has been registered for driver [sqlite].');

        $database->using();
    }

    #[Test]
    public function it_builds_a_query_on_the_default_connection(): void
    {
        $database = $this->database();

        self::assertSame('users', $database->table('users')->toSelectQuery()->table);
        self::assertSame($this->grammar->result, $database->table('users')->compile());
    }

    #[Test]
    public function it_builds_a_query_on_a_named_connection(): void
    {
        $mysql = new RecordingGrammar();

        $database = new Database(
            $this->manager($this->config('default'), $this->config('reporting', DriverName::MySql)),
            new QueryGrammarResolver([
                DriverName::SQLite->value => $this->grammar,
                DriverName::MySql->value => $mysql,
            ]),
        );

        $database->table('orders', 'reporting')->compile();

        self::assertNotNull($mysql->select);
        self::assertSame('orders', $mysql->select->table);
    }

    #[Test]
    public function it_rejects_an_empty_table(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A query table cannot be empty.');

        $this->database()->table('');
    }

    #[Test]
    public function it_executes_a_query_on_the_default_connection(): void
    {
        $database = $this->database();

        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
        $database->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);

        self::assertSame([['name' => 'Ada']], $database->execute('SELECT name FROM users')->all());
    }

    #[Test]
    public function it_executes_a_query_on_a_named_connection(): void
    {
        $database = $this->database($this->manager($this->config('default'), $this->config('reporting')));

        $database->execute('CREATE TABLE reports (id INTEGER PRIMARY KEY)', connection: 'reporting');

        self::assertSame([], $database->execute('SELECT * FROM reports', connection: 'reporting')->all());
    }

    #[Test]
    public function it_keeps_named_connections_apart(): void
    {
        $database = $this->database($this->manager($this->config('default'), $this->config('reporting')));

        $database->execute('CREATE TABLE reports (id INTEGER PRIMARY KEY)', connection: 'reporting');

        $this->expectException(QueryException::class);

        $database->execute('SELECT * FROM reports');
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
    public function it_passes_the_scoped_database_to_the_transaction_callback(): void
    {
        $database = $this->database();

        $scope = $database->transaction(static fn(ConnectedDatabase $scope) => $scope);

        self::assertInstanceOf(ConnectedDatabase::class, $scope);
        self::assertSame($database->connection(), $scope->connection());
    }

    #[Test]
    public function it_commits_a_transaction(): void
    {
        $database = $this->database();

        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $database->transaction(static function (ConnectedDatabase $scope): void {
            $scope->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);
        });

        self::assertSame([['name' => 'Ada']], $database->execute('SELECT name FROM users')->all());
    }

    #[Test]
    public function it_rolls_back_a_failed_transaction(): void
    {
        $database = $this->database();

        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        try {
            $database->transaction(static function (ConnectedDatabase $scope): void {
                $scope->execute('INSERT INTO users (name) VALUES (?)', ['Ada']);

                throw new RuntimeException('The callback failed.');
            });
        } catch (RuntimeException $exception) {
            self::assertSame('The callback failed.', $exception->getMessage());
        }

        self::assertSame([], $database->execute('SELECT name FROM users')->all());
    }

    #[Test]
    public function it_runs_a_transaction_on_a_named_connection(): void
    {
        $database = $this->database($this->manager($this->config('default'), $this->config('reporting')));

        $database->execute('CREATE TABLE reports (id INTEGER PRIMARY KEY, label TEXT)', connection: 'reporting');

        $database->transaction(static function (ConnectedDatabase $scope): void {
            $scope->execute('INSERT INTO reports (label) VALUES (?)', ['weekly']);
        }, 'reporting');

        self::assertSame(
            [['label' => 'weekly']],
            $database->execute('SELECT label FROM reports', connection: 'reporting')->all(),
        );
    }

    #[Test]
    public function it_builds_queries_inside_a_transaction(): void
    {
        $database = $this->database();

        $database->execute('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');

        $this->grammar->result = new CompiledQuery('INSERT INTO users (name) VALUES (?)', ['Ada']);

        $affected = $database->transaction(static fn(ConnectedDatabase $scope): int => $scope
            ->table('users')
            ->insert(['name' => 'Ada']));

        self::assertSame(1, $affected);
        self::assertSame([['name' => 'Ada']], $database->execute('SELECT name FROM users')->all());
    }
}
