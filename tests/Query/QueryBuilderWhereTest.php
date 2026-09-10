<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query;

use ArgumentCountError;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Database\Query\Clause\WhereIn;
use Dirthara\Database\Query\Clause\RawWhere;
use Dirthara\Database\Query\Clause\WhereNull;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Query\Clause\NestedWhere;
use Dirthara\Database\Query\Clause\WhereColumn;
use Dirthara\Database\Query\Clause\WhereExists;
use Dirthara\Database\Query\Clause\WhereBetween;
use Dirthara\Database\Query\Sql\BooleanOperator;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Database\Query\Expression\RawExpression;

final class QueryBuilderWhereTest extends QueryBuilderTestCase
{
    #[Test]
    public function it_has_no_conditions_by_default(): void
    {
        self::assertSame([], $this->builder()->toSelectQuery()->wheres);
    }

    #[Test]
    public function it_compares_a_column_to_a_value(): void
    {
        $wheres = $this->builder()->where('name', '=', 'Ada')->toSelectQuery()->wheres;

        self::assertCount(1, $wheres);

        $where = self::clause(Where::class, $wheres[0]);

        self::assertSame('name', self::identifier($where->column)->name);
        self::assertSame(ComparisonOperator::Equal, $where->operator);
        self::assertSame('Ada', $where->value);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_compares_a_column_to_a_value_as_an_alternative(): void
    {
        $wheres = $this->builder()->where('name', '=', 'Ada')->orWhere('name', '=', 'Grace')->toSelectQuery()->wheres;

        self::assertCount(2, $wheres);
        self::assertSame(BooleanOperator::Or, self::clause(Where::class, $wheres[1])->boolean);
    }

    #[Test]
    public function it_accepts_an_operator_instance(): void
    {
        $wheres = $this->builder()->where('age', ComparisonOperator::GreaterThanOrEqual, 18)->toSelectQuery()->wheres;

        self::assertSame(ComparisonOperator::GreaterThanOrEqual, self::clause(Where::class, $wheres[0])->operator);
    }

    /**
     * @return array<string, array{string, ComparisonOperator}>
     */
    public static function operators(): array
    {
        return [
            'lower case like' => ['like', ComparisonOperator::Like],
            'mixed case like' => ['Like', ComparisonOperator::Like],
            'upper case like' => ['LIKE', ComparisonOperator::Like],
            'lower case not like' => ['not like', ComparisonOperator::NotLike],
            'greater than' => ['>', ComparisonOperator::GreaterThan],
            'less than or equal' => ['<=', ComparisonOperator::LessThanOrEqual],
            'not equal' => ['!=', ComparisonOperator::NotEqual],
        ];
    }

    #[Test]
    #[DataProvider('operators')]
    public function it_reads_an_operator_in_any_case(string $operator, ComparisonOperator $expected): void
    {
        $wheres = $this->builder()->where('name', $operator, 'Ada')->toSelectQuery()->wheres;

        self::assertSame($expected, self::clause(Where::class, $wheres[0])->operator);
    }

    #[Test]
    public function it_rejects_an_unknown_operator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The operator [equals] cannot compare two values; expected one of =, !=, >, >=, <, <=, LIKE, NOT LIKE.',
        );

        $this->builder()->where('name', 'equals', 'Ada');
    }

    #[Test]
    public function it_rejects_an_operator_that_needs_its_own_clause(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The operator [IN] cannot compare two values; expected one of =, !=, >, >=, <, <=, LIKE, NOT LIKE.',
        );

        $this->builder()->where('id', 'IN', [1, 2]);
    }

    #[Test]
    public function it_keeps_a_condition_expression_as_given(): void
    {
        $expression = new RawExpression('LOWER(name)');

        $wheres = $this->builder()->where($expression, '=', 'ada')->toSelectQuery()->wheres;

        self::assertSame($expression, self::clause(Where::class, $wheres[0])->column);
    }

    #[Test]
    public function it_turns_an_equality_against_null_into_a_null_test(): void
    {
        $wheres = $this->builder()->where('deleted_at', '=', null)->toSelectQuery()->wheres;

        $where = self::clause(WhereNull::class, $wheres[0]);

        self::assertSame('deleted_at', self::identifier($where->column)->name);
        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_turns_an_inequality_against_null_into_a_negated_null_test(): void
    {
        $wheres = $this->builder()->orWhere(new Identifier('deleted_at'), '!=', null)->toSelectQuery()->wheres;

        $where = self::clause(WhereNull::class, $wheres[0]);

        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_needs_a_value_to_compare(): void
    {
        $this->expectException(ArgumentCountError::class);

        // @mago-expect analysis:too-few-arguments
        $this->builder()->where('id', '=');
    }

    #[Test]
    public function it_rejects_null_for_any_other_operator(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Operator [GreaterThan (>)] cannot be used with NULL.');

        $this->builder()->where('age', '>', null);
    }

    #[Test]
    public function it_adds_a_raw_condition(): void
    {
        $wheres = $this->builder()->whereRaw('LOWER(name) = ?', ['ada'])->toSelectQuery()->wheres;

        $where = self::clause(RawWhere::class, $wheres[0]);

        self::assertSame('LOWER(name) = ?', $where->sql);
        self::assertSame(['ada'], $where->bindings);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_adds_a_raw_condition_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereRaw('a = ?', [1])->toSelectQuery()->wheres;

        self::assertSame(BooleanOperator::Or, self::clause(RawWhere::class, $wheres[0])->boolean);
    }

    #[Test]
    public function a_raw_condition_needs_no_bindings(): void
    {
        $wheres = $this->builder()->whereRaw('deleted_at IS NULL')->toSelectQuery()->wheres;

        self::assertSame([], self::clause(RawWhere::class, $wheres[0])->bindings);
    }

    #[Test]
    public function it_tests_for_null(): void
    {
        $wheres = $this->builder()->whereNull('deleted_at')->toSelectQuery()->wheres;

        $where = self::clause(WhereNull::class, $wheres[0]);

        self::assertSame('deleted_at', self::identifier($where->column)->name);
        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_tests_for_null_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereNull('deleted_at')->toSelectQuery()->wheres;

        $where = self::clause(WhereNull::class, $wheres[0]);

        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_tests_for_a_value(): void
    {
        $expression = new Identifier('deleted_at');

        $wheres = $this->builder()->whereNotNull($expression)->toSelectQuery()->wheres;

        $where = self::clause(WhereNull::class, $wheres[0]);

        self::assertSame($expression, $where->column);
        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_tests_for_a_value_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereNotNull('deleted_at')->toSelectQuery()->wheres;

        $where = self::clause(WhereNull::class, $wheres[0]);

        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_tests_for_membership(): void
    {
        $wheres = $this->builder()->whereIn('id', [1, 2, 3])->toSelectQuery()->wheres;

        $where = self::clause(WhereIn::class, $wheres[0]);

        self::assertSame('id', self::identifier($where->column)->name);
        self::assertSame([1, 2, 3], $where->values);
        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_tests_for_membership_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereIn('id', [1])->toSelectQuery()->wheres;

        $where = self::clause(WhereIn::class, $wheres[0]);

        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_tests_for_exclusion(): void
    {
        $wheres = $this->builder()->whereNotIn('id', [1])->toSelectQuery()->wheres;

        $where = self::clause(WhereIn::class, $wheres[0]);

        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_tests_for_exclusion_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereNotIn(new Identifier('id'), [1])->toSelectQuery()->wheres;

        $where = self::clause(WhereIn::class, $wheres[0]);

        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_reindexes_membership_values(): void
    {
        $wheres = $this->builder()->whereIn('id', ['a' => 1, 'b' => 2])->toSelectQuery()->wheres;

        self::assertSame([1, 2], self::clause(WhereIn::class, $wheres[0])->values);
    }

    /**
     * @return iterable<string, int>
     */
    public static function membership(): iterable
    {
        yield 'a' => 1;

        yield 'b' => 2;
    }

    #[Test]
    public function it_collects_membership_values_from_any_iterable(): void
    {
        $wheres = $this->builder()->whereIn('id', self::membership())->toSelectQuery()->wheres;

        self::assertSame([1, 2], self::clause(WhereIn::class, $wheres[0])->values);
    }

    #[Test]
    public function it_records_an_empty_membership_test(): void
    {
        $wheres = $this->builder()->whereIn('id', [])->toSelectQuery()->wheres;

        self::assertSame([], self::clause(WhereIn::class, $wheres[0])->values);
    }

    #[Test]
    public function it_tests_a_range(): void
    {
        $wheres = $this->builder()->whereBetween('age', 18, 65)->toSelectQuery()->wheres;

        $where = self::clause(WhereBetween::class, $wheres[0]);

        self::assertSame('age', self::identifier($where->column)->name);
        self::assertSame(18, $where->from);
        self::assertSame(65, $where->to);
        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_tests_outside_a_range(): void
    {
        $expression = new Identifier('age');

        $wheres = $this->builder()->whereNotBetween($expression, 18, 65)->toSelectQuery()->wheres;

        $where = self::clause(WhereBetween::class, $wheres[0]);

        self::assertSame($expression, $where->column);
        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_tests_a_range_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereBetween('age', 18, 65)->toSelectQuery()->wheres;

        $where = self::clause(WhereBetween::class, $wheres[0]);

        self::assertSame(18, $where->from);
        self::assertSame(65, $where->to);
        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_tests_outside_a_range_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereNotBetween('age', 18, 65)->toSelectQuery()->wheres;

        $where = self::clause(WhereBetween::class, $wheres[0]);

        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_compares_two_columns(): void
    {
        $wheres = $this->builder()->whereColumn('created_at', '<', 'updated_at')->toSelectQuery()->wheres;

        $where = self::clause(WhereColumn::class, $wheres[0]);

        self::assertSame('created_at', self::identifier($where->first)->name);
        self::assertSame(ComparisonOperator::LessThan, $where->operator);
        self::assertSame('updated_at', self::identifier($where->second)->name);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_compares_two_columns_as_an_alternative(): void
    {
        $first = new Identifier('created_at');
        $second = new Identifier('updated_at');

        $wheres = $this
            ->builder()
            ->orWhereColumn($first, ComparisonOperator::NotEqual, $second)
            ->toSelectQuery()
            ->wheres;

        $where = self::clause(WhereColumn::class, $wheres[0]);

        self::assertSame($first, $where->first);
        self::assertSame($second, $where->second);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_reads_a_column_comparison_operator_in_any_case(): void
    {
        $wheres = $this->builder()->whereColumn('name', 'like', 'nickname')->toSelectQuery()->wheres;

        self::assertSame(ComparisonOperator::Like, self::clause(WhereColumn::class, $wheres[0])->operator);
    }

    #[Test]
    public function it_groups_nested_conditions(): void
    {
        $wheres = $this
            ->builder()
            ->where('active', '=', 1)
            ->whereNested(static function (QueryBuilder $query): void {
                $query->where('name', '=', 'Ada')->orWhere('name', '=', 'Grace');
            })
            ->toSelectQuery()
            ->wheres;

        self::assertCount(2, $wheres);

        $nested = self::clause(NestedWhere::class, $wheres[1]);

        self::assertCount(2, $nested->wheres);
        self::assertSame(BooleanOperator::And, $nested->boolean);
        self::assertSame(BooleanOperator::And, self::clause(Where::class, $nested->wheres[0])->boolean);
        self::assertSame(BooleanOperator::Or, self::clause(Where::class, $nested->wheres[1])->boolean);
    }

    #[Test]
    public function it_groups_nested_conditions_as_an_alternative(): void
    {
        $wheres = $this
            ->builder()
            ->orWhereNested(static function (QueryBuilder $query): void {
                $query->whereNull('deleted_at');
            })
            ->toSelectQuery()
            ->wheres;

        self::assertSame(BooleanOperator::Or, self::clause(NestedWhere::class, $wheres[0])->boolean);
    }

    #[Test]
    public function it_discards_an_empty_nested_group(): void
    {
        $wheres = $this
            ->builder()
            ->whereNested(static function (QueryBuilder $query): void {
                $query->select('id');
            })
            ->toSelectQuery()
            ->wheres;

        self::assertSame([], $wheres);
    }

    #[Test]
    public function it_nests_over_the_same_table(): void
    {
        $tables = [];

        $this->builder('posts')->whereNested(static function (QueryBuilder $query) use (&$tables): void {
            $tables[] = $query->toSelectQuery()->table;

            $query->whereNull('deleted_at');
        });

        self::assertEquals([new Identifier('posts')], $tables);
    }

    #[Test]
    public function it_builds_a_subquery_from_the_builder_itself(): void
    {
        $builder = $this->builder('users');

        $wheres = $builder
            ->whereExists($builder->newQuery('posts')->select('id')->whereColumn('posts.user_id', '=', 'users.id'))
            ->toSelectQuery()->wheres;

        $where = self::clause(WhereExists::class, $wheres[0]);

        self::assertEquals(new Identifier('posts'), $where->query->table);
        self::assertCount(1, $where->query->wheres);
    }

    #[Test]
    public function it_tests_for_a_matching_subquery(): void
    {
        $subquery = $this->builder('posts')->select('id')->whereColumn('posts.user_id', '=', 'users.id');

        $wheres = $this->builder()->whereExists($subquery)->toSelectQuery()->wheres;

        $where = self::clause(WhereExists::class, $wheres[0]);

        self::assertEquals(new Identifier('posts'), $where->query->table);
        self::assertCount(1, $where->query->wheres);
        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_tests_for_a_missing_subquery(): void
    {
        $wheres = $this->builder()->whereNotExists($this->builder('posts'))->toSelectQuery()->wheres;

        $where = self::clause(WhereExists::class, $wheres[0]);

        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::And, $where->boolean);
    }

    #[Test]
    public function it_tests_for_a_matching_subquery_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereExists($this->builder('posts'))->toSelectQuery()->wheres;

        $where = self::clause(WhereExists::class, $wheres[0]);

        self::assertEquals(new Identifier('posts'), $where->query->table);
        self::assertFalse($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_tests_for_a_missing_subquery_as_an_alternative(): void
    {
        $wheres = $this->builder()->orWhereNotExists($this->builder('posts'))->toSelectQuery()->wheres;

        $where = self::clause(WhereExists::class, $wheres[0]);

        self::assertTrue($where->negated);
        self::assertSame(BooleanOperator::Or, $where->boolean);
    }

    #[Test]
    public function it_returns_itself_from_every_condition_method(): void
    {
        $builder = $this->builder();

        self::assertSame($builder, $builder->where('name', '=', 'Ada'));
        self::assertSame($builder, $builder->orWhere('name', '=', 'Ada'));
        self::assertSame($builder, $builder->whereRaw('1 = 1'));
        self::assertSame($builder, $builder->orWhereRaw('1 = 1'));
        self::assertSame($builder, $builder->whereNull('deleted_at'));
        self::assertSame($builder, $builder->orWhereNull('deleted_at'));
        self::assertSame($builder, $builder->whereNotNull('deleted_at'));
        self::assertSame($builder, $builder->orWhereNotNull('deleted_at'));
        self::assertSame($builder, $builder->whereIn('id', [1]));
        self::assertSame($builder, $builder->orWhereIn('id', [1]));
        self::assertSame($builder, $builder->whereNotIn('id', [1]));
        self::assertSame($builder, $builder->orWhereNotIn('id', [1]));
        self::assertSame($builder, $builder->whereBetween('age', 1, 2));
        self::assertSame($builder, $builder->orWhereBetween('age', 1, 2));
        self::assertSame($builder, $builder->whereNotBetween('age', 1, 2));
        self::assertSame($builder, $builder->orWhereNotBetween('age', 1, 2));
        self::assertSame($builder, $builder->whereColumn('a', '=', 'b'));
        self::assertSame($builder, $builder->orWhereColumn('a', '=', 'b'));
        self::assertSame($builder, $builder->whereNested(static fn(QueryBuilder $query) => $query->whereNull('a')));
        self::assertSame($builder, $builder->orWhereNested(static fn(QueryBuilder $query) => $query->whereNull('a')));
        self::assertSame($builder, $builder->whereExists($this->builder('posts')));
        self::assertSame($builder, $builder->orWhereExists($this->builder('posts')));
        self::assertSame($builder, $builder->whereNotExists($this->builder('posts')));
        self::assertSame($builder, $builder->orWhereNotExists($this->builder('posts')));
    }
}
