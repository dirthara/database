<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Grammar;

use PHPUnit\Framework\TestCase;
use Dirthara\Database\Query\Join\JoinType;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Join\JoinClause;
use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Operator\ComparisonOperator;

abstract class GrammarTestCase extends TestCase
{
    /**
     * @param list<Expression> $columns
     * @param list<JoinClause> $joins
     * @param list<WhereClause> $wheres
     * @param list<Expression> $groups
     * @param list<WhereClause> $havings
     * @param list<OrderBy> $orders
     */
    protected function select(
        string|Expression $table = 'users',
        array $columns = [],
        array $joins = [],
        array $wheres = [],
        array $groups = [],
        array $havings = [],
        array $orders = [],
        ?int $limit = null,
        ?int $offset = null,
    ): SelectQuery {
        return new SelectQuery(
            table: Identifier::wrap($table),
            columns: $columns === [] ? [new Identifier('*')] : $columns,
            joins: $joins,
            wheres: $wheres,
            groups: $groups,
            havings: $havings,
            orders: $orders,
            limit: $limit,
            offset: $offset,
        );
    }

    protected function join(JoinType $type, string $table = 'posts'): JoinClause
    {
        return new JoinClause(
            table: Identifier::wrap($table),
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
        return array_map(Identifier::wrap(...), array_values($columns));
    }
}
