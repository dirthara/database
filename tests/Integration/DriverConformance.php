<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Integration;

use PDO;
use RuntimeException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Database\Query\Sql\LockMode;
use Dirthara\Database\Query\Sql\LockWait;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\PdoConnection;
use Dirthara\Database\Exception\QueryException;
use Dirthara\Database\Exception\RowLockException;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Lock\AcquiredLock;
use Dirthara\Database\Exception\NamedLockException;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;

use function getenv;
use function usleep;
use function sprintf;
use function in_array;
use function is_scalar;
use function str_repeat;
use function gc_collect_cycles;

/**
 * The behaviour every driver owes its caller, run against a real database.
 *
 * A driver that assembles a DSN is not a driver that assembled the right one, and
 * savepoint grammar cannot be judged without a server that accepts or rejects it.
 * Each subclass supplies a connection and the dialect of its schema; the tests here
 * are the same for all of them.
 *
 * @require-extends TestCase
 */
trait DriverConformance
{
    private ?Connection $connection = null;

    abstract protected function driverName(): DriverName;

    abstract protected function driver(): Driver;

    abstract protected function config(?string $charset = null): ConnectionConfig;

    /**
     * A users table with an auto-incrementing id, a text name, and a boolean active flag.
     */
    abstract protected function usersTable(): string;

    abstract protected function queryGrammar(): QueryGrammar;

    protected function supportsRowLocks(): bool
    {
        return true;
    }

    protected function supportsNamedLocks(): bool
    {
        return true;
    }

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

    #[Test]
    public function a_row_locked_for_update_cannot_be_locked_again_without_waiting(): void
    {
        $this->seedRowLockTable();
        $other = $this->otherSession();

        try {
            $this->connection()->transactions()->begin();
            self::assertSame('Ada', $this->lockedName($this->connection(), 1, LockMode::Update));

            $other->transactions()->begin();

            try {
                $this->lockedName($other, 1, LockMode::Update, LockWait::NoWait);

                self::fail('Expected the second session to be refused the row.');
            } catch (QueryException $exception) {
                self::assertSame('execute', $exception->context['operation']);
            }
        } finally {
            $this->abandon($other, $this->connection());
        }
    }

    #[Test]
    public function skip_locked_leaves_out_the_row_another_session_holds(): void
    {
        $this->seedRowLockTable();
        $other = $this->otherSession();

        try {
            $this->connection()->transactions()->begin();
            $this->lockedName($this->connection(), 1, LockMode::Update);

            $other->transactions()->begin();

            self::assertSame(['Grace', 'Linus'], $this->lockedNames($other, LockMode::Update, LockWait::SkipLocked));
            self::assertSame(
                'Grace',
                $this->table($other)->forUpdate(LockWait::SkipLocked)->orderBy('id')->first()['name'] ?? null,
            );
        } finally {
            $this->abandon($other, $this->connection());
        }
    }

    #[Test]
    public function rows_another_session_did_not_lock_stay_available(): void
    {
        $this->seedRowLockTable();
        $other = $this->otherSession();

        try {
            $this->connection()->transactions()->begin();
            $this->lockedName($this->connection(), 1, LockMode::Update);

            $other->transactions()->begin();

            self::assertSame('Grace', $this->lockedName($other, 2, LockMode::Update, LockWait::NoWait));
            self::assertSame('Linus', $this->lockedName($other, 3, LockMode::Share, LockWait::NoWait));
        } finally {
            $this->abandon($other, $this->connection());
        }
    }

    #[Test]
    public function a_commit_releases_the_row_lock(): void
    {
        $this->seedRowLockTable();
        $other = $this->otherSession();

        try {
            $this->connection()->transactions()->begin();
            $this->lockedName($this->connection(), 1, LockMode::Update);
            $this->connection()->transactions()->commit();

            $other->transactions()->begin();

            self::assertSame('Ada', $this->lockedName($other, 1, LockMode::Update, LockWait::NoWait));
        } finally {
            $this->abandon($other, $this->connection());
        }
    }

    #[Test]
    public function a_rollback_releases_the_row_lock(): void
    {
        $this->seedRowLockTable();
        $other = $this->otherSession();

        try {
            $this->connection()->transactions()->begin();
            $this->lockedName($this->connection(), 1, LockMode::Update);
            $this->connection()->transactions()->rollback();

            $other->transactions()->begin();

            self::assertSame(
                ['Ada', 'Grace', 'Linus'],
                $this->lockedNames($other, LockMode::Update, LockWait::SkipLocked),
            );
        } finally {
            $this->abandon($other, $this->connection());
        }
    }

    #[Test]
    public function share_locks_are_compatible_with_each_other_but_not_with_an_update_lock(): void
    {
        $this->seedRowLockTable();
        $other = $this->otherSession();

        try {
            $this->connection()->transactions()->begin();
            self::assertSame('Ada', $this->lockedName($this->connection(), 1, LockMode::Share));

            $other->transactions()->begin();

            self::assertSame('Ada', $this->lockedName($other, 1, LockMode::Share, LockWait::NoWait));
            self::assertSame(['Grace', 'Linus'], $this->lockedNames($other, LockMode::Update, LockWait::SkipLocked));

            try {
                $this->lockedName($other, 1, LockMode::Update, LockWait::NoWait);

                self::fail('Expected an update lock to conflict with a share lock.');
            } catch (QueryException) {
                self::assertTrue($other->transactions()->inTransaction());
            }
        } finally {
            $this->abandon($other, $this->connection());
        }
    }

    #[Test]
    public function an_update_lock_blocks_a_share_lock(): void
    {
        $this->seedRowLockTable();
        $other = $this->otherSession();

        try {
            $this->connection()->transactions()->begin();
            $this->lockedName($this->connection(), 1, LockMode::Update);

            $other->transactions()->begin();

            self::assertSame(['Grace', 'Linus'], $this->lockedNames($other, LockMode::Share, LockWait::SkipLocked));

            try {
                $this->lockedName($other, 1, LockMode::Share, LockWait::NoWait);

                self::fail('Expected a share lock to conflict with an update lock.');
            } catch (QueryException) {
                self::assertTrue($other->transactions()->inTransaction());
            }
        } finally {
            $this->abandon($other, $this->connection());
        }
    }

    #[Test]
    public function a_locked_read_outside_a_transaction_is_refused_before_it_reaches_the_database(): void
    {
        $this->seedRowLockTable();

        $this->expectException(RowLockException::class);

        $this->table($this->connection())->forUpdate()->where('id', '=', 1)->get();
    }

    private function seedRowLockTable(): void
    {
        if (!$this->supportsRowLocks()) {
            self::markTestSkipped(sprintf('%s does not support pessimistic row locks.', $this->driverName()->name));
        }

        $this->insert('Ada');
        $this->insert('Grace');
        $this->insert('Linus');
    }

    private function otherSession(): Connection
    {
        return new PdoConnection($this->config(), $this->driver());
    }

    private function table(Connection $connection): QueryBuilder
    {
        return new QueryBuilder($connection, $this->queryGrammar(), 'users');
    }

    private function lockedName(Connection $connection, int $id, LockMode $mode, LockWait $wait = LockWait::Wait): mixed
    {
        return (
            $this->table($connection)->select('name')->lock($mode, $wait)->where('id', '=', $id)->first()['name']
            ?? null
        );
    }

    /**
     * @return list<mixed>
     */
    private function lockedNames(Connection $connection, LockMode $mode, LockWait $wait): array
    {
        $names = [];

        foreach ($this->table($connection)->select('name')->lock($mode, $wait)->orderBy('id')->get() as $row) {
            $names[] = $row['name'];
        }

        return $names;
    }

    private function abandon(Connection ...$connections): void
    {
        foreach ($connections as $connection) {
            while ($connection->transactions()->inTransaction()) {
                $connection->transactions()->rollback();
            }
        }
    }

    #[Test]
    public function a_named_lock_held_by_one_session_is_contended_for_another(): void
    {
        $this->requireNamedLocks();
        $other = $this->otherSession();
        $locks = [];

        try {
            $alpha = $this->connection()->locks()->acquire('dirthara:conformance:alpha');
            $locks[] = $alpha;

            self::assertNull($other->locks()->tryAcquire('dirthara:conformance:alpha'));

            $beta = $other->locks()->tryAcquire('dirthara:conformance:beta');
            $locks[] = $beta;
            self::assertSame('dirthara:conformance:beta', $beta?->name);

            $alpha->release();
            self::assertTrue($alpha->released);

            $taken = $other->locks()->tryAcquire('dirthara:conformance:alpha');
            $locks[] = $taken;
            self::assertSame('dirthara:conformance:alpha', $taken?->name);
            self::assertSame(['dirthara:conformance:beta', 'dirthara:conformance:alpha'], $other->locks()->held());
        } finally {
            $this->releaseAll(...$locks);
        }
    }

    #[Test]
    public function a_named_lock_is_not_reentrant_on_one_connection(): void
    {
        $this->requireNamedLocks();
        $other = $this->otherSession();
        $locks = [];

        try {
            $alpha = $this->connection()->locks()->acquire('dirthara:conformance:alpha');
            $locks[] = $alpha;

            try {
                $this->connection()->locks()->tryAcquire('dirthara:conformance:alpha');

                self::fail('Expected a NamedLockException.');
            } catch (NamedLockException $exception) {
                self::assertSame('dirthara:conformance:alpha', $exception->context['lock']);
            }

            $alpha->release();

            $taken = $other->locks()->tryAcquire('dirthara:conformance:alpha');
            $locks[] = $taken;
            self::assertNotNull($taken, 'The refused second attempt must not leave a native lock count behind.');
        } finally {
            $this->releaseAll(...$locks);
        }
    }

    #[Test]
    public function releasing_a_named_lock_twice_is_refused(): void
    {
        $this->requireNamedLocks();
        $other = $this->otherSession();
        $locks = [];

        try {
            $alpha = $this->connection()->locks()->acquire('dirthara:conformance:alpha');
            $alpha->release();

            try {
                $alpha->release();

                self::fail('Expected a NamedLockException.');
            } catch (NamedLockException $exception) {
                self::assertSame('release_lock', $exception->context['operation']);
            }

            $taken = $other->locks()->tryAcquire('dirthara:conformance:alpha');
            $locks[] = $taken;
            self::assertNotNull($taken);
        } finally {
            $this->releaseAll(...$locks);
        }
    }

    #[Test]
    public function a_named_lock_outlives_a_rollback_and_a_commit(): void
    {
        $this->requireNamedLocks();
        $other = $this->otherSession();
        $locks = [];

        try {
            $transactions = $this->connection()->transactions();

            $transactions->begin();
            $alpha = $this->connection()->locks()->acquire('dirthara:conformance:alpha');
            $locks[] = $alpha;
            $transactions->rollback();

            self::assertNull($other->locks()->tryAcquire('dirthara:conformance:alpha'));

            $transactions->begin();
            $transactions->commit();

            self::assertNull($other->locks()->tryAcquire('dirthara:conformance:alpha'));

            $alpha->release();

            $taken = $other->locks()->tryAcquire('dirthara:conformance:alpha');
            $locks[] = $taken;
            self::assertNotNull($taken);
        } finally {
            $this->abandon($this->connection());
            $this->releaseAll(...$locks);
        }
    }

    #[Test]
    public function long_names_that_differ_only_at_the_end_are_different_locks(): void
    {
        $this->requireNamedLocks();
        $other = $this->otherSession();
        $locks = [];
        $prefix = str_repeat('dirthara:conformance:', times: 20);

        try {
            $locks[] = $this
                ->connection()
                ->locks()
                ->acquire($prefix . 'one');

            $two = $other->locks()->tryAcquire($prefix . 'two');
            $locks[] = $two;
            self::assertSame($prefix . 'two', $two?->name);
            self::assertNull($other->locks()->tryAcquire($prefix . 'one'));
        } finally {
            $this->releaseAll(...$locks);
        }
    }

    #[Test]
    public function a_named_lock_ends_with_the_session_that_held_it(): void
    {
        $this->requireNamedLocks();
        $other = $this->otherSession();
        $locks = [];

        try {
            $holder = $this->otherSession();
            $lock = $holder->locks()->acquire('dirthara:conformance:alpha');

            self::assertNull($other->locks()->tryAcquire('dirthara:conformance:alpha'));

            unset($lock, $holder);
            gc_collect_cycles();

            $taken = null;

            for ($i = 0; $i < 50 && $taken === null; $i++) {
                $taken = $other->locks()->tryAcquire('dirthara:conformance:alpha');

                if ($taken === null) {
                    usleep(100_000);
                }
            }

            $locks[] = $taken;
            self::assertNotNull($taken, 'The server did not release the lock of a closed session.');
        } finally {
            $this->releaseAll(...$locks);
        }
    }

    private function requireNamedLocks(): void
    {
        if (!$this->supportsNamedLocks()) {
            self::markTestSkipped(sprintf('%s does not support named locks.', $this->driverName()->name));
        }
    }

    private function releaseAll(?AcquiredLock ...$locks): void
    {
        foreach ($locks as $lock) {
            if ($lock === null || $lock->released) {
                continue;
            }

            $lock->release();
        }
    }
}
