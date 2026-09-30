<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query;

use Closure;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\Clause\Lock;
use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Database\Query\Sql\LockMode;
use Dirthara\Database\Query\Sql\LockWait;
use Dirthara\Database\Connection\Connection;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Exception\RowLockException;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Database\Exception\UnsupportedLockException;
use Dirthara\Database\Tests\Fixtures\Query\BuildsQueries;

use function iterator_to_array;

final class QueryBuilderLockTest extends TestCase
{
    use BuildsQueries;

    #[Test]
    public function it_starts_without_a_lock(): void
    {
        self::assertNull($this->builder()->toSelectQuery()->lock);
    }

    #[Test]
    public function it_locks_for_update_and_waits_by_default(): void
    {
        self::assertEquals(
            new Lock(LockMode::Update, LockWait::Wait),
            $this->builder()->forUpdate()->toSelectQuery()->lock,
        );
    }

    #[Test]
    public function it_locks_for_update_without_waiting_or_skipping_locked_rows(): void
    {
        self::assertEquals(
            new Lock(LockMode::Update, LockWait::NoWait),
            $this->builder()->forUpdate(LockWait::NoWait)->toSelectQuery()->lock,
        );
        self::assertEquals(
            new Lock(LockMode::Update, LockWait::SkipLocked),
            $this->builder()->forUpdate(LockWait::SkipLocked)->toSelectQuery()->lock,
        );
    }

    #[Test]
    public function it_locks_for_share(): void
    {
        self::assertEquals(
            new Lock(LockMode::Share, LockWait::Wait),
            $this->builder()->forShare()->toSelectQuery()->lock,
        );
        self::assertEquals(
            new Lock(LockMode::Share, LockWait::NoWait),
            $this->builder()->forShare(LockWait::NoWait)->toSelectQuery()->lock,
        );
    }

    #[Test]
    public function it_locks_with_a_mode_and_a_wait(): void
    {
        self::assertEquals(
            new Lock(LockMode::Share, LockWait::SkipLocked),
            $this->builder()->lock(LockMode::Share, LockWait::SkipLocked)->toSelectQuery()->lock,
        );
    }

    #[Test]
    public function the_last_lock_wins(): void
    {
        self::assertEquals(
            new Lock(LockMode::Share, LockWait::Wait),
            $this->builder()->forUpdate(LockWait::SkipLocked)->forShare()->toSelectQuery()->lock,
        );
    }

    #[Test]
    public function it_removes_a_lock(): void
    {
        self::assertNull($this->builder()->forUpdate()->withoutLock()->toSelectQuery()->lock);
    }

    #[Test]
    public function a_clone_keeps_its_lock(): void
    {
        $builder = $this->builder()->forUpdate(LockWait::NoWait);
        $clone = clone $builder;

        $builder->withoutLock();

        self::assertEquals(new Lock(LockMode::Update, LockWait::NoWait), $clone->toSelectQuery()->lock);
    }

    #[Test]
    public function a_new_query_starts_without_a_lock(): void
    {
        self::assertNull($this->builder()->forUpdate()->newQuery('posts')->toSelectQuery()->lock);
    }

    /**
     * @return iterable<string, array{Closure(QueryBuilder): mixed}>
     */
    public static function rowReads(): iterable
    {
        yield 'get' => [static fn(QueryBuilder $builder): mixed => $builder->get()];
        yield 'first' => [static fn(QueryBuilder $builder): mixed => $builder->first()];
        yield 'cursor' => [static fn(QueryBuilder $builder): mixed => iterator_to_array($builder->cursor())];
    }

    /**
     * @param Closure(QueryBuilder): mixed $read
     */
    #[Test]
    #[DataProvider('rowReads')]
    public function it_refuses_to_run_a_locked_read_outside_a_transaction(Closure $read): void
    {
        $connection = $this->seeded();

        try {
            $read($this->locked($connection, 'SELECT * FROM a_table_that_does_not_exist'));

            self::fail('Expected a RowLockException.');
        } catch (RowLockException $exception) {
            self::assertSame(
                'A query with a pessimistic row lock must run inside a transaction on connection "testing", or the '
                . 'lock ends with the statement.',
                $exception->getMessage(),
            );
            self::assertSame(
                ['connection' => 'testing', 'lock_mode' => 'update', 'lock_wait' => 'skip_locked'],
                $exception->context,
            );
        }
    }

    /**
     * @param Closure(QueryBuilder): mixed $read
     */
    #[Test]
    #[DataProvider('rowReads')]
    public function it_runs_a_locked_read_inside_a_transaction(Closure $read): void
    {
        $connection = $this->seeded();

        /** @var array<array-key, mixed>|null $result */
        $result = $connection->transactions()->run(fn(): mixed => $read($this->locked(
            $connection,
            'SELECT name FROM users ORDER BY id',
        )));

        self::assertNotEmpty($result);
        self::assertEquals(new Lock(LockMode::Update, LockWait::SkipLocked), $this->grammar->select?->lock);
    }

    #[Test]
    public function first_keeps_the_lock_while_it_limits_the_query(): void
    {
        $connection = $this->seeded();

        $connection->transactions()->run(
            fn(): mixed => $this->locked($connection, 'SELECT name FROM users ORDER BY id')->first(),
        );

        self::assertNotNull($this->grammar->select);
        self::assertSame(1, $this->grammar->select->limit);
        self::assertEquals(new Lock(LockMode::Update, LockWait::SkipLocked), $this->grammar->select->lock);
    }

    /**
     * @return iterable<string, array{string, Closure(QueryBuilder): mixed}>
     */
    public static function writesAndPages(): iterable
    {
        yield 'chunk' => [
            'chunk()',
            static fn(QueryBuilder $builder): mixed => $builder->orderBy('id')->chunk(1, static fn(): null => null),
        ];
        yield 'insert' => [
            'insert()',
            static fn(QueryBuilder $builder): mixed => $builder->insert(['name' => 'Linus']),
        ];
        yield 'insertGetId' => [
            'insertGetId()',
            static fn(QueryBuilder $builder): mixed => $builder->insertGetId(['name' => 'Linus']),
        ];
        yield 'update' => ['update()', static fn(QueryBuilder $builder): mixed => $builder->update(['active' => 0])];
        yield 'delete' => ['delete()', static fn(QueryBuilder $builder): mixed => $builder->delete()];
    }

    /**
     * @param Closure(QueryBuilder): mixed $operation
     */
    #[Test]
    #[DataProvider('writesAndPages')]
    public function it_refuses_an_operation_a_row_lock_means_nothing_to(string $name, Closure $operation): void
    {
        $connection = $this->seeded();

        try {
            $connection->transactions()->run(fn(): mixed => $operation($this->locked($connection, 'SELECT 1')));

            self::fail('Expected an UnsupportedLockException.');
        } catch (UnsupportedLockException $exception) {
            self::assertSame(
                'A query with a pessimistic row lock cannot run '
                . $name
                . '; lock rows with get(), first(), or cursor().',
                $exception->getMessage(),
            );
            self::assertSame($name, $exception->context['operation']);
        }

        self::assertSame(['Ada', 'Grace'], $connection->execute('SELECT name FROM users ORDER BY id')->column('name'));
    }

    /**
     * @return iterable<string, array{string, Closure(QueryBuilder): mixed}>
     */
    public static function aggregates(): iterable
    {
        yield 'exists' => ['exists()', static fn(QueryBuilder $builder): mixed => $builder->exists()];
        yield 'count' => ['count()', static fn(QueryBuilder $builder): mixed => $builder->count()];
        yield 'sum' => ['sum()', static fn(QueryBuilder $builder): mixed => $builder->sum('active')];
        yield 'avg' => ['avg()', static fn(QueryBuilder $builder): mixed => $builder->avg('active')];
        yield 'min' => ['min()', static fn(QueryBuilder $builder): mixed => $builder->min('active')];
        yield 'max' => ['max()', static fn(QueryBuilder $builder): mixed => $builder->max('active')];
    }

    /**
     * @param Closure(QueryBuilder): mixed $operation
     */
    #[Test]
    #[DataProvider('aggregates')]
    public function it_refuses_an_aggregate_over_locked_rows(string $name, Closure $operation): void
    {
        $this->expectException(UnsupportedLockException::class);
        $this->expectExceptionMessageIs(
            'A query with a pessimistic row lock cannot run ' . $name . '; lock rows with get(), first(), or cursor().',
        );

        $operation(new QueryBuilder($this->seeded(), new SQLiteQueryGrammar(), 'users')->forUpdate());
    }

    #[Test]
    public function sqlite_refuses_the_lock_before_checking_for_a_transaction(): void
    {
        $this->expectException(UnsupportedLockException::class);
        $this->expectExceptionMessageIs('SQLite does not support pessimistic row locks.');

        new QueryBuilder($this->seeded(), new SQLiteQueryGrammar(), 'users')->forUpdate()->get();
    }

    #[Test]
    public function it_compiles_a_locked_query_for_inspection_outside_a_transaction(): void
    {
        $this->grammar->result = new CompiledQuery('SELECT * FROM users FOR UPDATE');

        self::assertSame('SELECT * FROM users FOR UPDATE', $this->builder()->forUpdate()->toSql());
    }

    private function locked(Connection $connection, string $sql): QueryBuilder
    {
        $this->grammar->result = new CompiledQuery($sql);

        return new QueryBuilder($connection, $this->grammar, 'users')->forUpdate(LockWait::SkipLocked);
    }
}
