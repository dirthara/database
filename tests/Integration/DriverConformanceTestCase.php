<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Integration;

use PDO;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

use function getenv;
use function sprintf;
use function in_array;
use function is_scalar;

/**
 * The behaviour every driver owes its caller, run against a real database.
 *
 * A driver that assembles a DSN is not a driver that assembled the right one, and
 * savepoint grammar cannot be judged without a server that accepts or rejects it.
 * Each subclass supplies a connection and the dialect of its schema; the tests here
 * are the same for all of them.
 */
abstract class DriverConformanceTestCase extends TestCase
{
    private ?Connection $connection = null;

    abstract protected function driverName(): DriverName;

    abstract protected function driver(): Driver;

    abstract protected function config(?string $charset = null): ConnectionConfig;

    /**
     * A users table with an auto-incrementing id, a text name, and a boolean active flag.
     */
    abstract protected function usersTable(): string;

    /**
     * The sequence lastInsertId() needs, for databases that cannot answer without one.
     */
    protected function sequence(): ?string
    {
        return null;
    }

    protected function grammar(): TransactionGrammar
    {
        return new StandardTransactionGrammar(new SavepointPrefix());
    }

    protected function setUp(): void
    {
        if (!in_array($this->driverName()->value, PDO::getAvailableDrivers(), strict: true)) {
            self::markTestSkipped(sprintf('The %s PDO driver is not installed.', $this->driverName()->value));
        }

        $this->connection = new PdoConnection($this->config(), $this->driver());

        $this->connection->execute('DROP TABLE IF EXISTS users');
        $this->connection->execute($this->usersTable());
    }

    protected function connection(): Connection
    {
        self::assertNotNull($this->connection);

        return $this->connection;
    }

    protected function env(string $name, string $default): string
    {
        $value = getenv($name);

        return $value === false || $value === '' ? $default : $value;
    }

    /**
     * @param array<int|string, scalar|null> $parameters
     *
     * @return array<string, mixed>
     */
    protected function firstRow(string $query, array $parameters = []): array
    {
        $row = $this->connection()->execute($query, $parameters)->first();

        self::assertNotNull($row);

        return $row;
    }

    /**
     * @return list<mixed>
     */
    private function names(): array
    {
        return $this->connection()->execute('SELECT name FROM users ORDER BY id')->column('name');
    }

    private function insert(string $name, ?bool $active = true): void
    {
        $this->connection()->execute('INSERT INTO users (name, active) VALUES (?, ?)', [$name, $active]);
    }

    #[Test]
    public function it_connects_and_round_trips_a_query(): void
    {
        self::assertSame(1, (int) $this->firstRow('SELECT 1 AS one')['one']);
    }

    #[Test]
    public function it_binds_positional_parameters(): void
    {
        $this->insert('Ada');
        $this->insert('Grace');

        self::assertSame(
            ['Ada'],
            $this->connection()->execute('SELECT name FROM users WHERE name = ?', ['Ada'])->column('name'),
        );
    }

    #[Test]
    public function it_binds_named_parameters(): void
    {
        $this->connection()->execute('INSERT INTO users (name, active) VALUES (:name, :active)', [
            'name' => 'Ada',
            'active' => true,
        ]);

        self::assertSame(['Ada'], $this->names());
    }

    #[Test]
    public function it_binds_a_boolean_and_a_null(): void
    {
        $this->insert('Ada', active: true);
        $this->insert('Grace', active: null);

        self::assertTrue($this->activeFlag('Ada'));
        self::assertNull($this->firstRow('SELECT active FROM users WHERE name = ?', ['Grace'])['active']);
    }

    /**
     * Each database reports its own boolean type, so compare what the value means
     * rather than what it is: PostgreSQL answers with a bool, MySQL and SQLite with
     * an int, and SQL Server with a bit.
     */
    private function activeFlag(string $name): bool
    {
        /** @var scalar|null $value */
        $value = $this->firstRow('SELECT active FROM users WHERE name = ?', [$name])['active'];

        if (!is_scalar($value)) {
            self::fail('Expected a scalar active flag.');
        }

        return (bool) $value;
    }

    #[Test]
    public function it_reads_a_result_forward_only(): void
    {
        $this->insert('Ada');
        $this->insert('Grace');

        $result = $this->connection()->execute('SELECT name FROM users ORDER BY id');

        self::assertSame(['name' => 'Ada'], $result->first());
        self::assertSame([['name' => 'Grace']], $result->all());
    }

    #[Test]
    public function it_reads_a_column_by_position_and_by_name(): void
    {
        $this->insert('Ada');

        self::assertSame(['Ada'], $this->connection()->execute('SELECT name FROM users')->column());
        self::assertSame(['Ada'], $this->connection()->execute('SELECT name FROM users')->column('name'));
    }

    #[Test]
    public function it_streams_rows_without_buffering_them(): void
    {
        $this->insert('Ada');
        $this->insert('Grace');

        $names = [];

        foreach ($this->connection()->execute('SELECT name FROM users ORDER BY id')->iterate() as $row) {
            $names[] = $row['name'];
        }

        self::assertSame(['Ada', 'Grace'], $names);
    }

    #[Test]
    public function it_reports_the_rows_a_write_affected(): void
    {
        $this->insert('Ada');
        $this->insert('Grace');

        self::assertSame(2, $this->connection()->execute('DELETE FROM users')->affectedRows());
    }

    #[Test]
    public function it_reports_the_last_insert_id(): void
    {
        $this->insert('Ada');

        $id = $this->connection()->lastInsertId($this->sequence());

        self::assertNotNull($id);
        self::assertSame(1, (int) $this->firstRow('SELECT id FROM users WHERE id = ?', [(int) $id])['id']);
    }

    #[Test]
    public function it_commits_a_transaction(): void
    {
        $this
            ->connection()
            ->transactions()
            ->run(function (): void {
                $this->insert('Ada');
            });

        self::assertSame(['Ada'], $this->names());
    }

    #[Test]
    public function it_rolls_back_a_transaction_when_the_callback_throws(): void
    {
        try {
            $this
                ->connection()
                ->transactions()
                ->run(function (): void {
                    $this->insert('Ada');

                    throw new RuntimeException('no');
                });
        } catch (RuntimeException) {
            self::assertSame([], $this->names());
        }
    }

    #[Test]
    public function it_rolls_back_only_the_inner_savepoint(): void
    {
        $this
            ->connection()
            ->transactions()
            ->run(function (): void {
                $this->insert('Ada');

                try {
                    $this
                        ->connection()
                        ->transactions()
                        ->run(function (): void {
                            $this->insert('Grace');

                            throw new RuntimeException('no');
                        });
                } catch (RuntimeException) {
                    // The savepoint absorbed it, so the enclosing transaction continues.
                    self::assertSame(1, $this->connection()->transactions()->level());
                }
            });

        self::assertSame(['Ada'], $this->names());
    }

    #[Test]
    public function it_releases_a_savepoint_when_a_nested_transaction_commits(): void
    {
        $transactions = $this->connection()->transactions();

        self::assertSame(0, $transactions->level());

        $transactions->run(function () use ($transactions): void {
            self::assertSame(1, $transactions->level());

            $transactions->run(function () use ($transactions): void {
                self::assertSame(2, $transactions->level());

                $this->insert('Ada');
            });

            self::assertSame(1, $transactions->level());
        });

        self::assertSame(0, $transactions->level());
        self::assertFalse($transactions->inTransaction());
        self::assertSame(['Ada'], $this->names());
    }
}
