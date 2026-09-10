<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query;

use LogicException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Aggregate\AggregateFunction;

final class QueryBuilderExecutionTest extends QueryBuilderTestCase
{
    #[Test]
    public function it_returns_every_row(): void
    {
        $builder = $this->executing('SELECT name FROM users ORDER BY id');

        self::assertSame([['name' => 'Ada'], ['name' => 'Grace']], $builder->get());
    }

    #[Test]
    public function it_returns_an_empty_result(): void
    {
        self::assertSame([], $this->executing('SELECT name FROM users WHERE 0')->get());
    }

    #[Test]
    public function it_passes_the_compiled_bindings_to_the_connection(): void
    {
        $builder = $this->executing('SELECT name FROM users WHERE name = ?', ['Grace']);

        self::assertSame([['name' => 'Grace']], $builder->get());
    }

    #[Test]
    public function it_hands_the_select_query_to_the_grammar(): void
    {
        $this->executing('SELECT name FROM users')->select('name')->where('active', '=', 1)->get();

        self::assertNotNull($this->grammar->select);
        self::assertEquals(new Identifier('users'), $this->grammar->select->table);
        self::assertSame('name', self::identifier($this->grammar->select->columns[0])->name);
        self::assertCount(1, $this->grammar->select->wheres);
    }

    #[Test]
    public function it_streams_every_row(): void
    {
        $builder = $this->executing('SELECT name FROM users ORDER BY id');

        self::assertSame([['name' => 'Ada'], ['name' => 'Grace']], iterator_to_array($builder->cursor()));
    }

    #[Test]
    public function it_only_compiles_a_cursor_once_it_is_iterated(): void
    {
        $cursor = $this->executing('SELECT name FROM users')->cursor();

        self::assertNull($this->grammar->select);

        iterator_to_array($cursor);

        self::assertNotNull($this->grammar->select);
    }

    #[Test]
    public function it_returns_the_first_row(): void
    {
        $builder = $this->executing('SELECT name FROM users ORDER BY id');

        self::assertSame(['name' => 'Ada'], $builder->first());
    }

    #[Test]
    public function it_returns_null_when_there_is_no_first_row(): void
    {
        self::assertNull($this->executing('SELECT name FROM users WHERE 0')->first());
    }

    #[Test]
    public function it_limits_the_first_row_to_one(): void
    {
        $this->executing('SELECT name FROM users')->where('active', '=', 1)->first();

        self::assertNotNull($this->grammar->select);
        self::assertSame(1, $this->grammar->select->limit);
        self::assertCount(1, $this->grammar->select->wheres);
    }

    #[Test]
    public function it_leaves_the_builder_untouched_when_taking_the_first_row(): void
    {
        $builder = $this->executing('SELECT name FROM users')->limit(10);

        $builder->first();

        self::assertSame(10, $builder->toSelectQuery()->limit);
    }

    #[Test]
    public function it_reports_that_rows_exist(): void
    {
        self::assertTrue($this->executing('SELECT 1 AS present FROM users LIMIT 1')->exists());
    }

    #[Test]
    public function it_reports_that_no_rows_exist(): void
    {
        self::assertFalse($this->executing('SELECT 1 AS present FROM users WHERE 0')->exists());
    }

    #[Test]
    public function it_reads_existence_from_the_first_column(): void
    {
        self::assertFalse($this->executing('SELECT 0 AS present')->exists());
    }

    #[Test]
    public function it_hands_the_select_query_to_the_exists_grammar(): void
    {
        $this->executing('SELECT 1 AS present')->where('active', '=', 1)->exists();

        self::assertNotNull($this->grammar->exists);
        self::assertCount(1, $this->grammar->exists->wheres);
    }

    #[Test]
    public function it_counts_rows(): void
    {
        self::assertSame(2, $this->executing('SELECT COUNT(*) AS aggregate FROM users')->count());
    }

    #[Test]
    public function it_counts_no_rows(): void
    {
        self::assertSame(0, $this->executing('SELECT COUNT(*) AS aggregate FROM users WHERE 0')->count());
    }

    #[Test]
    public function it_counts_zero_when_the_aggregate_returns_no_row(): void
    {
        self::assertSame(0, $this->executing('SELECT 1 AS aggregate FROM users WHERE 0')->count());
    }

    #[Test]
    public function it_counts_every_column_by_default(): void
    {
        $this->executing('SELECT COUNT(*) AS aggregate FROM users')->count();

        self::assertNotNull($this->grammar->countColumn);
        self::assertSame('*', self::identifier($this->grammar->countColumn)->name);
    }

    #[Test]
    public function it_counts_a_named_column(): void
    {
        $this->executing('SELECT COUNT(name) AS aggregate FROM users')->count('name');

        self::assertNotNull($this->grammar->countColumn);
        self::assertSame('name', self::identifier($this->grammar->countColumn)->name);
    }

    #[Test]
    public function it_counts_an_expression(): void
    {
        $column = new Identifier('DISTINCT name');

        $this->executing('SELECT COUNT(DISTINCT name) AS aggregate FROM users')->count($column);

        self::assertSame($column, $this->grammar->countColumn);
    }

    #[Test]
    public function it_hands_the_select_query_to_the_count_grammar(): void
    {
        $this->executing('SELECT COUNT(*) AS aggregate FROM users')->where('active', '=', 1)->count();

        self::assertNotNull($this->grammar->count);
        self::assertCount(1, $this->grammar->count->wheres);
    }

    #[Test]
    public function it_sums_a_column(): void
    {
        $builder = $this->executing('SELECT SUM(active) AS aggregate FROM users');

        self::assertSame(1, $builder->sum('active'));
        self::assertSame(AggregateFunction::Sum, $this->grammar->aggregateFunction);
        self::assertNotNull($this->grammar->countColumn);
        self::assertSame('active', self::identifier($this->grammar->countColumn)->name);
    }

    #[Test]
    public function it_averages_a_column(): void
    {
        self::assertSame(0.5, $this->executing('SELECT AVG(active) AS aggregate FROM users')->avg('active'));
        self::assertSame(AggregateFunction::Average, $this->grammar->aggregateFunction);
    }

    #[Test]
    public function it_takes_the_smallest_value(): void
    {
        self::assertSame('Ada', $this->executing('SELECT MIN(name) AS aggregate FROM users')->min('name'));
        self::assertSame(AggregateFunction::Minimum, $this->grammar->aggregateFunction);
    }

    #[Test]
    public function it_takes_the_largest_value(): void
    {
        self::assertSame('Grace', $this->executing('SELECT MAX(name) AS aggregate FROM users')->max('name'));
        self::assertSame(AggregateFunction::Maximum, $this->grammar->aggregateFunction);
    }

    #[Test]
    public function an_aggregate_over_no_rows_is_null(): void
    {
        self::assertNull($this->executing('SELECT MAX(name) AS aggregate FROM users WHERE 0')->max('name'));
    }

    #[Test]
    public function an_aggregate_without_a_row_is_null(): void
    {
        self::assertNull($this->executing('SELECT 1 AS aggregate FROM users WHERE 0')->max('name'));
    }

    #[Test]
    public function it_rejects_an_aggregate_over_a_grouped_query(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A grouped query has one SUM per group; add it to the selection instead.');

        $this->executing('SELECT SUM(active) AS aggregate FROM users')->groupBy('name')->sum('active');
    }

    #[Test]
    public function it_counts_a_grouped_query(): void
    {
        $this->executing('SELECT COUNT(*) AS aggregate FROM users')->groupBy('name')->count();

        self::assertSame(AggregateFunction::Count, $this->grammar->aggregateFunction);
    }

    #[Test]
    public function it_inserts_a_single_row(): void
    {
        $builder = $this->executing('INSERT INTO users (name, active) VALUES (?, ?)', ['Alan', 1]);

        self::assertSame(1, $builder->insert(['name' => 'Alan', 'active' => 1]));
    }

    #[Test]
    public function it_wraps_a_single_row_in_a_list(): void
    {
        $this->executing('INSERT INTO users (name) VALUES (?)', ['Alan'])->insert(['name' => 'Alan']);

        self::assertNotNull($this->grammar->insert);
        self::assertEquals(new Identifier('users'), $this->grammar->insert->table);
        self::assertSame([['name' => 'Alan']], $this->grammar->insert->rows);
    }

    #[Test]
    public function it_inserts_several_rows(): void
    {
        $rows = [['name' => 'Alan'], ['name' => 'Edsger']];

        $affected = $this->executing('INSERT INTO users (name) VALUES (?), (?)', ['Alan', 'Edsger'])->insert($rows);

        self::assertSame(2, $affected);
        self::assertNotNull($this->grammar->insert);
        self::assertSame($rows, $this->grammar->insert->rows);
    }

    #[Test]
    public function it_skips_an_insert_without_values(): void
    {
        self::assertSame(0, $this->executing('INSERT INTO users (name) VALUES (?)', ['Alan'])->insert([]));
        self::assertNull($this->grammar->insert);
    }

    #[Test]
    public function it_rejects_a_bulk_insert_that_is_not_a_list_of_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Bulk inserts must contain arrays of column values.');

        // @mago-expect analysis:possibly-invalid-argument
        $this->executing('INSERT INTO users (name) VALUES (?)')->insert([['name' => 'Alan'], 'Edsger']);
    }

    #[Test]
    public function it_updates_matching_rows(): void
    {
        $builder = $this->executing('UPDATE users SET active = ?', [0]);

        self::assertSame(2, $builder->update(['active' => 0]));
    }

    #[Test]
    public function it_hands_the_update_query_to_the_grammar(): void
    {
        $this
            ->executing('UPDATE users SET active = ? WHERE name = ?', [0, 'Ada'])
            ->where('name', '=', 'Ada')
            ->limit(1)
            ->update(['active' => 0]);

        self::assertNotNull($this->grammar->update);
        self::assertEquals(new Identifier('users'), $this->grammar->update->table);
        self::assertSame(['active' => 0], $this->grammar->update->values);
        self::assertSame(1, $this->grammar->update->limit);
        self::assertCount(1, $this->grammar->update->wheres);
        self::assertSame(
            'name',
            self::identifier(self::clause(Where::class, $this->grammar->update->wheres[0])->column)->name,
        );
    }

    #[Test]
    public function it_skips_an_update_without_values(): void
    {
        self::assertSame(0, $this->executing('UPDATE users SET active = ?', [0])->update([]));
        self::assertNull($this->grammar->update);
    }

    #[Test]
    public function it_carries_the_ordering_into_an_update(): void
    {
        $this->executing('UPDATE users SET active = ?', [0])->orderBy('name')->limit(1)->update(['active' => 0]);

        self::assertNotNull($this->grammar->update);
        self::assertCount(1, $this->grammar->update->orders);
        self::assertSame(1, $this->grammar->update->limit);
    }

    #[Test]
    public function it_rejects_an_update_that_skips_rows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Update queries cannot skip rows with an offset.');

        $this->executing('UPDATE users SET active = ?')->offset(5)->update(['active' => 0]);
    }

    #[Test]
    public function it_rejects_an_update_over_a_join(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Joined update queries are not supported yet.');

        $this
            ->executing('UPDATE users SET active = ?')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->update(['active' => 0]);
    }

    #[Test]
    public function it_deletes_matching_rows(): void
    {
        self::assertSame(2, $this->executing('DELETE FROM users')->delete());
    }

    #[Test]
    public function it_hands_the_delete_query_to_the_grammar(): void
    {
        $this->executing('DELETE FROM users WHERE name = ?', ['Ada'])->where('name', '=', 'Ada')->limit(1)->delete();

        self::assertNotNull($this->grammar->delete);
        self::assertEquals(new Identifier('users'), $this->grammar->delete->table);
        self::assertSame(1, $this->grammar->delete->limit);
        self::assertCount(1, $this->grammar->delete->wheres);
    }

    #[Test]
    public function it_carries_the_ordering_into_a_delete(): void
    {
        $this->executing('DELETE FROM users')->orderByDesc('name')->limit(2)->delete();

        self::assertNotNull($this->grammar->delete);
        self::assertCount(1, $this->grammar->delete->orders);
        self::assertSame(2, $this->grammar->delete->limit);
    }

    #[Test]
    public function it_rejects_a_delete_that_skips_rows(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Delete queries cannot skip rows with an offset.');

        $this->executing('DELETE FROM users')->offset(5)->delete();
    }

    #[Test]
    public function it_rejects_a_delete_over_a_join(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Joined delete queries are not supported yet.');

        $this->executing('DELETE FROM users')->join('posts', 'users.id', '=', 'posts.user_id')->delete();
    }
}
