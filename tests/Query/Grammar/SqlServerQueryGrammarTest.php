<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Grammar;

use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Join\JoinType;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Clause\WhereIn;
use Dirthara\Database\Query\Clause\WhereNull;
use Dirthara\Database\Query\Clause\NestedWhere;
use Dirthara\Database\Query\Clause\WhereExists;
use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Clause\OrderDirection;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Operator\BooleanOperator;
use Dirthara\Database\Query\Operator\ComparisonOperator;
use Dirthara\Database\Query\Grammar\SqlServerQueryGrammar;

final class SqlServerQueryGrammarTest extends GrammarTestCase
{
    private SqlServerQueryGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new SqlServerQueryGrammar();
    }

    #[Test]
    public function it_selects_every_column(): void
    {
        $query = $this->grammar->compileSelect($this->select());

        self::assertSame('SELECT * FROM [users]', $query->sql);
        self::assertSame([], $query->bindings);
    }

    #[Test]
    public function it_quotes_identifiers_with_brackets(): void
    {
        self::assertSame(
            'SELECT [id], [users].[name] FROM [users]',
            $this->grammar->compileSelect($this->select(columns: $this->columns('id', 'users.name')))->sql,
        );
    }

    #[Test]
    public function it_keeps_a_wildcard_unquoted(): void
    {
        self::assertSame(
            'SELECT [users].* FROM [users]',
            $this->grammar->compileSelect($this->select(columns: $this->columns('users.*')))->sql,
        );
    }

    #[Test]
    public function it_escapes_a_closing_bracket_in_a_column_name(): void
    {
        self::assertSame(
            'INSERT INTO [users] ([we]]ird]) VALUES (?)',
            $this->grammar->compileInsert(new InsertQuery('users', [['we]ird' => 1]]))->sql,
        );
    }

    #[Test]
    public function it_emits_an_expression_that_is_not_an_identifier_as_written(): void
    {
        self::assertSame(
            'SELECT COUNT(*) AS total FROM [users]',
            $this->grammar->compileSelect($this->select(columns: $this->columns('COUNT(*) AS total')))->sql,
        );
    }

    #[Test]
    public function it_limits_rows_with_top(): void
    {
        self::assertSame(
            'SELECT TOP (10) * FROM [users]',
            $this->grammar->compileSelect($this->select(limit: 10))->sql,
        );
    }

    #[Test]
    public function it_limits_ordered_rows_with_top(): void
    {
        self::assertSame(
            'SELECT TOP (10) * FROM [users] ORDER BY [name] ASC',
            $this->grammar->compileSelect($this->select(orders: [new OrderBy(
                new Expression('name'),
                OrderDirection::Ascending,
            )], limit: 10))->sql,
        );
    }

    #[Test]
    public function it_pages_rows_with_offset_and_fetch(): void
    {
        self::assertSame(
            'SELECT * FROM [users] ORDER BY [name] ASC OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY',
            $this->grammar->compileSelect($this->select(
                orders: [new OrderBy(new Expression('name'), OrderDirection::Ascending)],
                limit: 10,
                offset: 20,
            ))->sql,
        );
    }

    #[Test]
    public function it_offsets_rows_without_a_limit(): void
    {
        self::assertSame(
            'SELECT * FROM [users] ORDER BY [name] ASC OFFSET 20 ROWS',
            $this->grammar->compileSelect($this->select(orders: [new OrderBy(
                new Expression('name'),
                OrderDirection::Ascending,
            )], offset: 20))->sql,
        );
    }

    #[Test]
    public function it_orders_a_paged_query_that_has_no_ordering(): void
    {
        self::assertSame(
            'SELECT * FROM [users] ORDER BY (SELECT NULL) OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY',
            $this->grammar->compileSelect($this->select(limit: 10, offset: 20))->sql,
        );
    }

    #[Test]
    public function it_leaves_an_unpaged_query_unordered(): void
    {
        self::assertSame('SELECT * FROM [users]', $this->grammar->compileSelect($this->select())->sql);
    }

    #[Test]
    public function it_compares_a_column_to_a_binding(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Expression('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
            new Where(new Expression('active'), ComparisonOperator::Equal, 1, BooleanOperator::Or),
        ]));

        self::assertSame('SELECT * FROM [users] WHERE [name] = ? OR [active] = ?', $query->sql);
        self::assertSame(['Ada', 1], $query->bindings);
    }

    #[Test]
    public function it_tests_for_null(): void
    {
        self::assertSame(
            'SELECT * FROM [users] WHERE [deleted_at] IS NOT NULL',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereNull(new Expression('deleted_at'), true, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_tests_for_membership(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereIn(new Expression('id'), [1, 2], false, BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM [users] WHERE [id] IN (?, ?)', $query->sql);
        self::assertSame([1, 2], $query->bindings);
    }

    #[Test]
    public function it_groups_nested_conditions(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Expression('active'), ComparisonOperator::Equal, 1, BooleanOperator::And),
            new NestedWhere([
                new Where(new Expression('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
                new Where(new Expression('name'), ComparisonOperator::Equal, 'Grace', BooleanOperator::Or),
            ], BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM [users] WHERE [active] = ? AND ([name] = ? OR [name] = ?)', $query->sql);
        self::assertSame([1, 'Ada', 'Grace'], $query->bindings);
    }

    #[Test]
    public function it_joins_a_table(): void
    {
        self::assertSame(
            'SELECT * FROM [users] FULL JOIN [posts] ON [users].[id] = [posts].[user_id]',
            $this->grammar->compileSelect($this->select(joins: [$this->join(JoinType::Full)]))->sql,
        );
    }

    #[Test]
    public function it_drops_the_ordering_of_an_exists_subquery(): void
    {
        self::assertSame(
            'SELECT * FROM [users] WHERE EXISTS (SELECT * FROM [posts])',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereExists(
                    $this->select(table: 'posts', orders: [new OrderBy(
                        new Expression('views'),
                        OrderDirection::Descending,
                    )]),
                    false,
                    BooleanOperator::And,
                ),
            ]))->sql,
        );
    }

    #[Test]
    public function it_keeps_the_ordering_of_a_paged_exists_subquery(): void
    {
        self::assertSame(
            'SELECT * FROM [users] WHERE EXISTS (SELECT TOP (1) * FROM [posts] ORDER BY [views] DESC)',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereExists(
                    $this->select(
                        table: 'posts',
                        orders: [new OrderBy(new Expression('views'), OrderDirection::Descending)],
                        limit: 1,
                    ),
                    false,
                    BooleanOperator::And,
                ),
            ]))->sql,
        );
    }

    #[Test]
    public function it_compiles_an_existence_check(): void
    {
        $query = $this->grammar->compileExists($this->select(wheres: [new Where(
            new Expression('active'),
            ComparisonOperator::Equal,
            1,
            BooleanOperator::And,
        )], orders: [new OrderBy(new Expression('name'), OrderDirection::Ascending)]));

        self::assertSame(
            'SELECT CASE WHEN EXISTS(SELECT * FROM [users] WHERE [active] = ?) THEN 1 ELSE 0 END AS [exists]',
            $query->sql,
        );
        self::assertSame([1], $query->bindings);
    }

    #[Test]
    public function it_counts_rows(): void
    {
        self::assertSame(
            'SELECT COUNT([name]) AS [aggregate] FROM [users]',
            $this->grammar->compileCount($this->select(), new Expression('name'))->sql,
        );
    }

    #[Test]
    public function it_drops_ordering_and_paging_from_a_count(): void
    {
        self::assertSame(
            'SELECT COUNT(*) AS [aggregate] FROM [users]',
            $this->grammar->compileCount(
                $this->select(
                    orders: [new OrderBy(new Expression('name'), OrderDirection::Ascending)],
                    limit: 10,
                    offset: 5,
                ),
                new Expression('*'),
            )->sql,
        );
    }

    #[Test]
    public function it_counts_the_groups_of_a_query(): void
    {
        self::assertSame(
            'SELECT COUNT(*) AS [aggregate] FROM (SELECT [role] FROM [users] GROUP BY [role]) AS [aggregate]',
            $this->grammar->compileCount($this->select(groups: $this->columns('role'), orders: [new OrderBy(
                new Expression('role'),
                OrderDirection::Ascending,
            )]), new Expression('*'))->sql,
        );
    }

    #[Test]
    public function it_orders_the_clauses_of_a_full_query(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            columns: $this->columns('users.name'),
            joins: [$this->join(JoinType::Inner)],
            wheres: [new Where(new Expression('users.active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
            groups: $this->columns('users.name'),
            havings: [new Where(new Expression('total'), ComparisonOperator::GreaterThan, 2, BooleanOperator::And)],
            orders: [new OrderBy(new Expression('users.name'), OrderDirection::Ascending)],
            limit: 5,
        ));

        self::assertSame(
            'SELECT TOP (5) [users].[name] FROM [users] INNER JOIN [posts] ON [users].[id] = [posts].[user_id]'
            . ' WHERE [users].[active] = ? GROUP BY [users].[name] HAVING [total] > ?'
            . ' ORDER BY [users].[name] ASC',
            $query->sql,
        );
        self::assertSame([1, 2], $query->bindings);
    }

    #[Test]
    public function it_inserts_several_rows(): void
    {
        $query = $this->grammar->compileInsert(new InsertQuery('users', [
            ['name' => 'Ada', 'active' => 1],
            ['name' => 'Grace', 'active' => 0],
        ]));

        self::assertSame('INSERT INTO [users] ([name], [active]) VALUES (?, ?), (?, ?)', $query->sql);
        self::assertSame(['Ada', 1, 'Grace', 0], $query->bindings);
    }

    #[Test]
    public function it_updates_rows(): void
    {
        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: 'users',
            values: ['active' => 0],
            wheres: [new Where(new Expression('id'), ComparisonOperator::Equal, 7, BooleanOperator::And)],
            limit: null,
        ));

        self::assertSame('UPDATE [users] SET [active] = ? WHERE [id] = ?', $query->sql);
        self::assertSame([0, 7], $query->bindings);
    }

    #[Test]
    public function it_limits_an_update_with_top(): void
    {
        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: 'users',
            values: ['active' => 0],
            wheres: [new Where(new Expression('active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
            limit: 1,
        ));

        self::assertSame('UPDATE TOP (1) [users] SET [active] = ? WHERE [active] = ?', $query->sql);
        self::assertSame([0, 1], $query->bindings);
    }

    #[Test]
    public function it_deletes_rows(): void
    {
        $query = $this->grammar->compileDelete(new DeleteQuery(
            table: 'users',
            wheres: [new Where(new Expression('id'), ComparisonOperator::Equal, 7, BooleanOperator::And)],
            limit: null,
        ));

        self::assertSame('DELETE FROM [users] WHERE [id] = ?', $query->sql);
        self::assertSame([7], $query->bindings);
    }

    #[Test]
    public function it_limits_a_delete_with_top(): void
    {
        self::assertSame(
            'DELETE TOP (5) FROM [users]',
            $this->grammar->compileDelete(new DeleteQuery(table: 'users', wheres: [], limit: 5))->sql,
        );
    }
}
