<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Grammar;

use LogicException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Operator\BooleanOperator;
use Dirthara\Database\Query\Operator\ComparisonOperator;
use Dirthara\Database\Tests\Query\Grammar\Doubles\UnsupportedWhere;
use Dirthara\Database\Tests\Query\Grammar\Doubles\StandardQueryGrammar;

final class SqlQueryGrammarTest extends GrammarTestCase
{
    private StandardQueryGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new StandardQueryGrammar();
    }

    #[Test]
    public function it_quotes_identifiers_with_double_quotes(): void
    {
        self::assertSame(
            'SELECT "users"."id" FROM "users"',
            $this->grammar->compileSelect($this->select(columns: $this->columns('users.id')))->sql,
        );
    }

    #[Test]
    public function it_escapes_a_double_quote_in_a_column_name(): void
    {
        self::assertSame(
            'INSERT INTO "users" ("we""ird") VALUES (?)',
            $this->grammar->compileInsert(new InsertQuery('users', [['we"ird' => 1]]))->sql,
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
    public function it_offsets_rows_without_a_limit(): void
    {
        self::assertSame(
            'SELECT * FROM "users" OFFSET 20',
            $this->grammar->compileSelect($this->select(offset: 20))->sql,
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
    public function it_updates_rows(): void
    {
        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: 'users',
            values: ['active' => 0],
            wheres: [new Where(new Expression('id'), ComparisonOperator::Equal, 7, BooleanOperator::And)],
            limit: null,
        ));

        self::assertSame('UPDATE "users" SET "active" = ? WHERE "id" = ?', $query->sql);
        self::assertSame([0, 7], $query->bindings);
    }

    #[Test]
    public function it_deletes_rows(): void
    {
        $query = $this->grammar->compileDelete(new DeleteQuery(
            table: 'users',
            wheres: [new Where(new Expression('id'), ComparisonOperator::Equal, 7, BooleanOperator::And)],
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

        $this->grammar->compileUpdate(new UpdateQuery(table: 'users', values: ['active' => 0], wheres: [], limit: 1));
    }

    #[Test]
    public function it_rejects_a_limited_delete(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A limited delete query is not supported by this driver.');

        $this->grammar->compileDelete(new DeleteQuery(table: 'users', wheres: [], limit: 1));
    }

    #[Test]
    public function it_rejects_an_unsupported_where_clause(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('Unsupported where clause [%s].', UnsupportedWhere::class));

        $this->grammar->compileSelect($this->select(wheres: [new UnsupportedWhere()]));
    }

    #[Test]
    public function it_rejects_an_unsupported_where_clause_after_the_first(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(sprintf('Unsupported where clause [%s].', UnsupportedWhere::class));

        $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Expression('active'), ComparisonOperator::Equal, 1, BooleanOperator::And),
            new UnsupportedWhere(),
        ]));
    }

    #[Test]
    public function it_rejects_an_insert_without_rows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An insert needs at least one row.');

        $this->grammar->compileInsert(new InsertQuery('users', []));
    }

    #[Test]
    public function it_rejects_an_insert_without_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An insert needs at least one column.');

        $this->grammar->compileInsert(new InsertQuery('users', [[]]));
    }

    #[Test]
    public function it_rejects_rows_with_different_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Every inserted row needs the same columns in the same order.');

        $this->grammar->compileInsert(new InsertQuery('users', [['name' => 'Ada'], ['active' => 1]]));
    }

    #[Test]
    public function it_rejects_rows_with_reordered_columns(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Every inserted row needs the same columns in the same order.');

        $this->grammar->compileInsert(new InsertQuery('users', [
            ['name' => 'Ada', 'active' => 1],
            ['active' => 0, 'name' => 'Grace'],
        ]));
    }

    #[Test]
    public function it_inserts_a_single_row_that_is_not_a_list(): void
    {
        $query = $this->grammar->compileInsert(new InsertQuery('users', ['name' => 'Ada']));

        self::assertSame('INSERT INTO "users" ("name") VALUES (?)', $query->sql);
        self::assertSame(['Ada'], $query->bindings);
    }

    #[Test]
    public function it_rejects_an_update_without_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An update needs at least one value.');

        $this->grammar->compileUpdate(new UpdateQuery(table: 'users', values: [], wheres: [], limit: null));
    }

    #[Test]
    public function it_rejects_a_binding_that_is_not_scalar(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A query binding must be scalar or null, got [stdClass].');

        $this->grammar->compileSelect($this->select(wheres: [
            new Where(new Expression('meta'), ComparisonOperator::Equal, new \stdClass(), BooleanOperator::And),
        ]));
    }
}
