<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Join\JoinType;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Query\Clause\OrderDirection;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Operator\BooleanOperator;
use Dirthara\Database\Query\Operator\ComparisonOperator;

final class QueryBuilderTest extends QueryBuilderTestCase
{
    #[Test]
    public function it_queries_the_given_table(): void
    {
        self::assertSame('users', $this->builder()->toSelectQuery()->table);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function emptyTables(): array
    {
        return [
            'empty' => [''],
            'spaces' => ['   '],
            'tab' => ["\t"],
        ];
    }

    #[Test]
    #[DataProvider('emptyTables')]
    public function it_rejects_an_empty_table(string $table): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A query table cannot be empty.');

        $this->builder($table);
    }

    #[Test]
    public function it_selects_every_column_by_default(): void
    {
        $columns = $this->builder()->toSelectQuery()->columns;

        self::assertCount(1, $columns);
        self::assertSame('*', $columns[0]->expression);
    }

    #[Test]
    public function it_wraps_selected_column_names_in_an_expression(): void
    {
        $columns = $this->builder()->select('id', 'name')->toSelectQuery()->columns;

        self::assertSame(['id', 'name'], array_map(static fn($column) => $column->expression, $columns));
    }

    #[Test]
    public function it_keeps_a_selected_expression_as_given(): void
    {
        $expression = new Expression('COUNT(*)');

        self::assertSame([$expression], $this->builder()->select($expression)->toSelectQuery()->columns);
    }

    #[Test]
    public function it_replaces_previously_selected_columns(): void
    {
        $columns = $this->builder()->select('id')->select('name')->toSelectQuery()->columns;

        self::assertSame(['name'], array_map(static fn($column) => $column->expression, $columns));
    }

    #[Test]
    public function it_falls_back_to_every_column_when_the_selection_is_cleared(): void
    {
        $columns = $this->builder()->select('id')->select()->toSelectQuery()->columns;

        self::assertSame(['*'], array_map(static fn($column) => $column->expression, $columns));
    }

    #[Test]
    public function it_appends_extra_selected_columns(): void
    {
        $columns = $this->builder()->select('id')->addSelect('name', 'email')->toSelectQuery()->columns;

        self::assertSame(['id', 'name', 'email'], array_map(static fn($column) => $column->expression, $columns));
    }

    #[Test]
    public function it_appends_extra_columns_to_an_empty_selection(): void
    {
        $columns = $this->builder()->addSelect(new Expression('name'))->toSelectQuery()->columns;

        self::assertSame(['name'], array_map(static fn($column) => $column->expression, $columns));
    }

    #[Test]
    public function it_groups_by_a_column_name(): void
    {
        $groups = $this->builder()->groupBy('role')->toSelectQuery()->groups;

        self::assertSame(['role'], array_map(static fn($group) => $group->expression, $groups));
    }

    #[Test]
    public function it_accumulates_groups_across_calls(): void
    {
        $groups = $this
            ->builder()
            ->groupBy('role')
            ->groupBy('team', new Expression('DATE(created_at)'))
            ->toSelectQuery()
            ->groups;

        self::assertSame(
            ['role', 'team', 'DATE(created_at)'],
            array_map(static fn($group) => $group->expression, $groups),
        );
    }

    #[Test]
    public function it_orders_ascending_by_default(): void
    {
        $orders = $this->builder()->orderBy('name')->toSelectQuery()->orders;

        self::assertCount(1, $orders);
        self::assertSame('name', $orders[0]->column->expression);
        self::assertSame(OrderDirection::Ascending, $orders[0]->direction);
    }

    #[Test]
    public function it_orders_by_an_explicit_direction(): void
    {
        $orders = $this->builder()->orderBy('name', OrderDirection::Descending)->toSelectQuery()->orders;

        self::assertSame(OrderDirection::Descending, $orders[0]->direction);
    }

    #[Test]
    public function it_orders_descending(): void
    {
        $expression = new Expression('created_at');

        $orders = $this->builder()->orderByDesc($expression)->toSelectQuery()->orders;

        self::assertSame($expression, $orders[0]->column);
        self::assertSame(OrderDirection::Descending, $orders[0]->direction);
    }

    #[Test]
    public function it_keeps_orders_in_the_order_they_were_added(): void
    {
        $orders = $this->builder()->orderBy('role')->orderByDesc('name')->toSelectQuery()->orders;

        self::assertSame(['role', 'name'], array_map(static fn($order) => $order->column->expression, $orders));
        self::assertSame(
            [OrderDirection::Ascending, OrderDirection::Descending],
            array_map(static fn($order) => $order->direction, $orders),
        );
    }

    #[Test]
    public function it_has_no_limit_or_offset_by_default(): void
    {
        $query = $this->builder()->toSelectQuery();

        self::assertNull($query->limit);
        self::assertNull($query->offset);
    }

    #[Test]
    public function it_limits_and_offsets_the_query(): void
    {
        $query = $this->builder()->limit(10)->offset(20)->toSelectQuery();

        self::assertSame(10, $query->limit);
        self::assertSame(20, $query->offset);
    }

    #[Test]
    public function it_accepts_a_zero_limit_and_offset(): void
    {
        $query = $this->builder()->limit(0)->offset(0)->toSelectQuery();

        self::assertSame(0, $query->limit);
        self::assertSame(0, $query->offset);
    }

    #[Test]
    public function it_rejects_a_negative_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query limit cannot be negative.');

        $this->builder()->limit(-1);
    }

    #[Test]
    public function it_rejects_a_negative_offset(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query offset cannot be negative.');

        $this->builder()->offset(-1);
    }

    #[Test]
    public function it_joins_on_two_columns(): void
    {
        $joins = $this->builder()->join('posts', 'users.id', '=', 'posts.user_id')->toSelectQuery()->joins;

        self::assertCount(1, $joins);
        self::assertSame('posts', $joins[0]->table);
        self::assertSame('users.id', $joins[0]->first->expression);
        self::assertSame(ComparisonOperator::Equal, $joins[0]->operator);
        self::assertSame('posts.user_id', $joins[0]->second->expression);
        self::assertSame(JoinType::Inner, $joins[0]->type);
    }

    #[Test]
    public function it_joins_with_an_explicit_type(): void
    {
        $joins = $this
            ->builder()
            ->join('posts', 'users.id', '=', 'posts.user_id', JoinType::Full)
            ->toSelectQuery()
            ->joins;

        self::assertSame(JoinType::Full, $joins[0]->type);
    }

    #[Test]
    public function it_keeps_join_expressions_as_given(): void
    {
        $first = new Expression('users.id');
        $second = new Expression('posts.user_id');

        $joins = $this->builder()->join('posts', $first, ComparisonOperator::NotEqual, $second)->toSelectQuery()->joins;

        self::assertSame($first, $joins[0]->first);
        self::assertSame($second, $joins[0]->second);
        self::assertSame(ComparisonOperator::NotEqual, $joins[0]->operator);
    }

    #[Test]
    public function it_left_joins(): void
    {
        $joins = $this->builder()->leftJoin('posts', 'users.id', '=', 'posts.user_id')->toSelectQuery()->joins;

        self::assertSame(JoinType::Left, $joins[0]->type);
    }

    #[Test]
    public function it_right_joins(): void
    {
        $joins = $this->builder()->rightJoin('posts', 'users.id', '=', 'posts.user_id')->toSelectQuery()->joins;

        self::assertSame(JoinType::Right, $joins[0]->type);
    }

    #[Test]
    public function it_reads_a_join_operator_in_any_case(): void
    {
        self::assertSame(
            ComparisonOperator::Like,
            $this->builder()->join('posts', 'users.name', 'like', 'posts.title')->toSelectQuery()->joins[0]->operator,
        );
        self::assertSame(
            ComparisonOperator::NotLike,
            $this
                ->builder()
                ->leftJoin('posts', 'users.name', 'not like', 'posts.title')
                ->toSelectQuery()
                ->joins[0]->operator,
        );
        self::assertSame(
            ComparisonOperator::Like,
            $this
                ->builder()
                ->rightJoin('posts', 'users.name', 'Like', 'posts.title')
                ->toSelectQuery()
                ->joins[0]->operator,
        );
    }

    #[Test]
    public function it_keeps_joins_in_the_order_they_were_added(): void
    {
        $joins = $this
            ->builder()
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->leftJoin('comments', 'posts.id', '=', 'comments.post_id')
            ->toSelectQuery()
            ->joins;

        self::assertSame(['posts', 'comments'], array_map(static fn($join) => $join->table, $joins));
    }

    #[Test]
    public function it_havings_on_an_aggregate(): void
    {
        $havings = $this->builder()->having('total', '>', 5)->toSelectQuery()->havings;

        self::assertCount(1, $havings);

        $having = self::clause(Where::class, $havings[0]);

        self::assertSame('total', $having->column->expression);
        self::assertSame(ComparisonOperator::GreaterThan, $having->operator);
        self::assertSame(5, $having->value);
        self::assertSame(BooleanOperator::And, $having->boolean);
    }

    #[Test]
    public function it_havings_on_an_alternative(): void
    {
        $havings = $this->builder()->having('total', '>', 5)->orHaving('total', '<', 1)->toSelectQuery()->havings;

        self::assertCount(2, $havings);
        self::assertSame(BooleanOperator::Or, self::clause(Where::class, $havings[1])->boolean);
    }

    #[Test]
    public function it_reads_a_having_operator_in_any_case(): void
    {
        $havings = $this->builder()->having('name', 'not like', 'a%')->toSelectQuery()->havings;

        self::assertSame(ComparisonOperator::NotLike, self::clause(Where::class, $havings[0])->operator);
    }

    #[Test]
    public function it_keeps_a_having_expression_as_given(): void
    {
        $expression = new Expression('COUNT(*)');

        $havings = $this->builder()->having($expression, ComparisonOperator::GreaterThan, 1)->toSelectQuery()->havings;

        self::assertSame($expression, self::clause(Where::class, $havings[0])->column);
    }

    #[Test]
    public function it_binds_a_null_having_value_instead_of_testing_for_null(): void
    {
        $havings = $this->builder()->having('total', '=')->toSelectQuery()->havings;

        $having = self::clause(Where::class, $havings[0]);

        self::assertNull($having->value);
        self::assertSame(ComparisonOperator::Equal, $having->operator);
    }

    #[Test]
    public function it_compiles_the_select_query_through_the_grammar(): void
    {
        $builder = $this->builder()->select('name')->limit(3);

        self::assertSame($this->grammar->result, $builder->compile());
        self::assertNotNull($this->grammar->select);
        self::assertSame(3, $this->grammar->select->limit);
        self::assertSame('name', $this->grammar->select->columns[0]->expression);
    }

    #[Test]
    public function it_exposes_the_compiled_sql(): void
    {
        $this->grammar->result = new CompiledQuery('SELECT * FROM users WHERE id = ?', [7]);

        self::assertSame('SELECT * FROM users WHERE id = ?', $this->builder()->toSql());
    }

    #[Test]
    public function it_exposes_the_compiled_bindings(): void
    {
        $this->grammar->result = new CompiledQuery('SELECT * FROM users WHERE id = ?', [7]);

        self::assertSame([7], $this->builder()->bindings());
    }

    #[Test]
    public function it_carries_every_clause_into_the_select_query(): void
    {
        $query = $this
            ->builder()
            ->select('name')
            ->join('posts', 'users.id', '=', 'posts.user_id')
            ->where('active', '=', 1)
            ->groupBy('role')
            ->having('total', '>', 2)
            ->orderBy('name')
            ->limit(5)
            ->offset(10)
            ->toSelectQuery();

        self::assertSame('users', $query->table);
        self::assertCount(1, $query->columns);
        self::assertCount(1, $query->joins);
        self::assertCount(1, $query->wheres);
        self::assertCount(1, $query->groups);
        self::assertCount(1, $query->havings);
        self::assertCount(1, $query->orders);
        self::assertSame(5, $query->limit);
        self::assertSame(10, $query->offset);
    }

    #[Test]
    public function it_returns_itself_from_every_clause_method(): void
    {
        $builder = $this->builder();

        self::assertSame($builder, $builder->select('id'));
        self::assertSame($builder, $builder->addSelect('name'));
        self::assertSame($builder, $builder->join('posts', 'users.id', '=', 'posts.user_id'));
        self::assertSame($builder, $builder->leftJoin('posts', 'users.id', '=', 'posts.user_id'));
        self::assertSame($builder, $builder->rightJoin('posts', 'users.id', '=', 'posts.user_id'));
        self::assertSame($builder, $builder->groupBy('role'));
        self::assertSame($builder, $builder->having('total', '>', 1));
        self::assertSame($builder, $builder->orHaving('total', '<', 9));
        self::assertSame($builder, $builder->orderBy('name'));
        self::assertSame($builder, $builder->orderByDesc('name'));
        self::assertSame($builder, $builder->limit(1));
        self::assertSame($builder, $builder->offset(1));
    }
}
