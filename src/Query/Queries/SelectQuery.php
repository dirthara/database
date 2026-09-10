<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Queries;

use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Join\JoinClause;
use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Expression\Expression;

final readonly class SelectQuery
{
    /**
     * @param list<Expression> $columns
     * @param list<JoinClause> $joins
     * @param list<WhereClause> $wheres
     * @param list<Expression> $groups
     * @param list<WhereClause> $havings
     * @param list<OrderBy> $orders
     * @param int|null $limit
     * @param int|null $offset
     */
    public function __construct(
        public string $table,
        public array $columns,
        public array $joins,
        public array $wheres,
        public array $groups,
        public array $havings,
        public array $orders,
        public ?int $limit,
        public ?int $offset,
    ) {}
}
