<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Grammar;

use PHPUnit\Framework\TestCase;
use Dirthara\Database\Query\Clause\Union;
use Dirthara\Database\Query\Sql\JoinType;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Clause\JoinClause;
use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Database\Query\Expression\ExpressionFactory;

abstract class GrammarTestCase extends TestCase
{
    /**
     * @param list<Expression> $columns
     * @param list<JoinClause> $joins
     * @param list<WhereClause> $wheres
     * @param list<Expression> $groups
     * @param list<WhereClause> $havings
     * @param list<Union> $unions
     * @param list<OrderBy> $orders
     */
    protected function select(
        string|Expression $table = 'users',
        array $columns = [],
        bool $distinct = false,
        array $joins = [],
        array $wheres = [],
        array $groups = [],
        array $havings = [],
        array $unions = [],
        array $orders = [],
        ?int $limit = null,
        ?int $offset = null,
    ): SelectQuery {
        return new SelectQuery(
            table: ExpressionFactory::from($table),
            columns: $columns === [] ? [new Identifier('*')] : $columns,
            distinct: $distinct,
            joins: $joins,
            wheres: $wheres,
            groups: $groups,
            havings: $havings,
            unions: $unions,
            orders: $orders,
            limit: $limit,
            offset: $offset,
        );
    }

    protected function join(JoinType $type, string $table = 'posts'): JoinClause
    {
        return new JoinClause(
            table: ExpressionFactory::from($table),
            first: new Identifier('users.id'),
            operator: ComparisonOperator::Equal,
            second: new Identifier($table . '.user_id'),
            type: $type,
        );
    }

    /**
     * @return list<Expression>
     */
    protected function columns(string ...$columns): array
    {
        return array_map(ExpressionFactory::from(...), array_values($columns));
    }
}
