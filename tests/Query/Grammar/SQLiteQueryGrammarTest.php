<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Grammar;

use LogicException;
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
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Expression\RawExpression;
use Dirthara\Database\Query\Operator\BooleanOperator;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Database\Query\Aggregate\AggregateFunction;
use Dirthara\Database\Query\Operator\ComparisonOperator;

final class SQLiteQueryGrammarTest extends GrammarTestCase
{
    private SQLiteQueryGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new SQLiteQueryGrammar();
    }

    #[Test]
    public function it_selects_every_column(): void
    {
        $query = $this->grammar->compileSelect($this->select());

        self::assertSame('SELECT * FROM "users"', $query->sql);
        self::assertSame([], $query->bindings);
    }

    #[Test]
    public function it_quotes_identifiers_with_double_quotes(): void
    {
        self::assertSame(
            'SELECT "id", "users"."name" FROM "users"',
            $this->grammar->compileSelect($this->select(columns: $this->columns('id', 'users.name')))->sql,
        );
    }

    #[Test]
    public function it_keeps_a_wildcard_unquoted(): void
    {
        self::assertSame(
            'SELECT "users".* FROM "users"',
            $this->grammar->compileSelect($this->select(columns: $this->columns('users.*')))->sql,
        );
    }

    #[Test]
    public function it_escapes_a_double_quote_in_a_column_name(): void
    {
        self::assertSame('INSERT INTO "users" ("we""ird") VALUES (?)', $this->grammar->compileInsert(new InsertQuery(
            new Identifier('users'),
            [['we"ird' => 1]],
        ))->sql);
    }

    #[Test]
    public function it_emits_a_raw_expression_as_written(): void
    {
        self::assertSame(
            'SELECT COUNT(*) AS total FROM "users"',
            $this->grammar->compileSelect($this->select(columns: [new RawExpression('COUNT(*) AS total')]))->sql,
        );
    }

    #[Test]
    public function it_limits_rows(): void
    {
        self::assertSame(
            'SELECT * FROM "users" LIMIT 10',
            $this->grammar->compileSelect($this->select(limit: 10))->sql,
        );
    }

    #[Test]
    public function it_limits_and_offsets_rows(): void
    {
        self::assertSame(
            'SELECT * FROM "users" LIMIT 10 OFFSET 20',
            $this->grammar->compileSelect($this->select(limit: 10, offset: 20))->sql,
        );
    }

    #[Test]
    public function it_offsets_rows_without_a_limit(): void
    {
        self::assertSame(
            'SELECT * FROM "users" LIMIT -1 OFFSET 20',
            $this->grammar->compileSelect($this->select(offset: 20))->sql,
        );
    }

    #[Test]
    public function it_compares_a_column_to_a_binding(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
            new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::Or),
        ]));

        self::assertSame('SELECT * FROM "users" WHERE "name" = ? OR "active" = ?', $query->sql);
        self::assertSame(['Ada', 1], $query->bindings);
    }

    #[Test]
    public function it_tests_for_null(): void
    {
        self::assertSame(
            'SELECT * FROM "users" WHERE "deleted_at" IS NOT NULL',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereNull(new Identifier('deleted_at'), true, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_tests_for_membership(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new WhereIn(new Identifier('id'), [1, 2], false, BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM "users" WHERE "id" IN (?, ?)', $query->sql);
        self::assertSame([1, 2], $query->bindings);
    }

    #[Test]
    public function it_never_matches_an_empty_membership_test(): void
    {
        self::assertSame(
            'SELECT * FROM "users" WHERE 1 = 0',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereIn(new Identifier('id'), [], false, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_groups_nested_conditions(): void
    {
        $query = $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Identifier('active'), ComparisonOperator::Equal, 1, BooleanOperator::And),
            new NestedWhere([
                new Where(new Identifier('name'), ComparisonOperator::Equal, 'Ada', BooleanOperator::And),
                new Where(new Identifier('name'), ComparisonOperator::Equal, 'Grace', BooleanOperator::Or),
            ], BooleanOperator::And),
        ]));

        self::assertSame('SELECT * FROM "users" WHERE "active" = ? AND ("name" = ? OR "name" = ?)', $query->sql);
        self::assertSame([1, 'Ada', 'Grace'], $query->bindings);
    }

    #[Test]
    public function it_tests_for_a_matching_subquery(): void
    {
        self::assertSame(
            'SELECT * FROM "users" WHERE EXISTS (SELECT * FROM "posts")',
            $this->grammar->compileSelect($this->select(wheres: [
                new WhereExists($this->select(table: new Identifier('posts')), false, BooleanOperator::And),
            ]))->sql,
        );
    }

    #[Test]
    public function it_joins_a_table(): void
    {
        self::assertSame(
            'SELECT * FROM "users" LEFT JOIN "posts" ON "users"."id" = "posts"."user_id"',
            $this->grammar->compileSelect($this->select(joins: [$this->join(JoinType::Left)]))->sql,
        );
    }

    #[Test]
    public function it_orders_the_clauses_of_a_full_query(): void
    {
        $query = $this->grammar->compileSelect($this->select(
            columns: $this->columns('users.name'),
            joins: [$this->join(JoinType::Inner)],
            wheres: [new Where(new Identifier('users.active'), ComparisonOperator::Equal, 1, BooleanOperator::And)],
            groups: $this->columns('users.name'),
            havings: [new Where(new Identifier('total'), ComparisonOperator::GreaterThan, 2, BooleanOperator::And)],
            orders: [new OrderBy(new Identifier('users.name'), OrderDirection::Ascending)],
            offset: 10,
        ));

        self::assertSame(
            'SELECT "users"."name" FROM "users" INNER JOIN "posts" ON "users"."id" = "posts"."user_id"'
            . ' WHERE "users"."active" = ? GROUP BY "users"."name" HAVING "total" > ?'
            . ' ORDER BY "users"."name" ASC LIMIT -1 OFFSET 10',
            $query->sql,
        );
        self::assertSame([1, 2], $query->bindings);
    }

    #[Test]
    public function it_compiles_an_existence_check(): void
    {
        $query = $this->grammar->compileExists($this->select(wheres: [new Where(
            new Identifier('active'),
            ComparisonOperator::Equal,
            1,
            BooleanOperator::And,
        )]));

        self::assertSame('SELECT EXISTS(SELECT * FROM "users" WHERE "active" = ?) AS "exists"', $query->sql);
        self::assertSame([1], $query->bindings);
    }

    #[Test]
    public function it_counts_rows(): void
    {
        self::assertSame(
            'SELECT COUNT("name") AS "aggregate" FROM "users"',
            $this->grammar->compileAggregate($this->select(), AggregateFunction::Count, new Identifier('name'))->sql,
        );
    }

    #[Test]
    public function it_counts_the_groups_of_a_query(): void
    {
        self::assertSame(
            'SELECT COUNT(*) AS "aggregate" FROM (SELECT "role" FROM "users" GROUP BY "role") AS "aggregate"',
            $this->grammar->compileAggregate(
                $this->select(groups: $this->columns('role')),
                AggregateFunction::Count,
                new Identifier('*'),
            )->sql,
        );
    }

    #[Test]
    public function it_inserts_several_rows(): void
    {
        $query = $this->grammar->compileInsert(new InsertQuery(new Identifier('users'), [
            ['name' => 'Ada', 'active' => 1],
            ['name' => 'Grace', 'active' => 0],
        ]));

        self::assertSame('INSERT INTO "users" ("name", "active") VALUES (?, ?), (?, ?)', $query->sql);
        self::assertSame(['Ada', 1, 'Grace', 0], $query->bindings);
    }

    #[Test]
    public function it_rejects_an_ordered_update(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An ordered update query is not supported by this driver.');

        $this->grammar->compileUpdate(new UpdateQuery(
            table: new Identifier('users'),
            values: ['active' => 0],
            wheres: [],
            orders: [new OrderBy(new Identifier('id'), OrderDirection::Ascending)],
            limit: null,
        ));
    }

    #[Test]
    public function it_rejects_an_ordered_delete(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('An ordered delete query is not supported by this driver.');

        $this->grammar->compileDelete(new DeleteQuery(
            table: new Identifier('users'),
            wheres: [],
            orders: [new OrderBy(new Identifier('id'), OrderDirection::Ascending)],
            limit: null,
        ));
    }

    #[Test]
    public function it_updates_rows(): void
    {
        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: new Identifier('users'),
            values: ['active' => 0],
            wheres: [new Where(new Identifier('id'), ComparisonOperator::Equal, 7, BooleanOperator::And)],
            orders: [],
            limit: null,
        ));

        self::assertSame('UPDATE "users" SET "active" = ? WHERE "id" = ?', $query->sql);
        self::assertSame([0, 7], $query->bindings);
    }

    #[Test]
    public function it_deletes_rows(): void
    {
        $query = $this->grammar->compileDelete(new DeleteQuery(
            table: new Identifier('users'),
            wheres: [new Where(new Identifier('id'), ComparisonOperator::Equal, 7, BooleanOperator::And)],
            orders: [],
            limit: null,
        ));

        self::assertSame('DELETE FROM "users" WHERE "id" = ?', $query->sql);
        self::assertSame([7], $query->bindings);
    }

    #[Test]
    public function it_rejects_a_limited_update(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A limited update query is not supported by this driver.');

        $this->grammar->compileUpdate(new UpdateQuery(
            table: new Identifier('users'),
            values: ['active' => 0],
            wheres: [],
            orders: [],
            limit: 1,
        ));
    }

    #[Test]
    public function it_rejects_a_limited_delete(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A limited delete query is not supported by this driver.');

        $this->grammar->compileDelete(new DeleteQuery(
            table: new Identifier('users'),
            wheres: [],
            orders: [],
            limit: 1,
        ));
    }
}
