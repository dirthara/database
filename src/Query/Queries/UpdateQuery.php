<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Queries;

use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Expression\Expression;

/**
 * @internal
 */
final readonly class UpdateQuery
{
    /**
     * @param array<string, scalar|null> $values
     * @param array<WhereClause> $wheres
     * @param list<OrderBy> $orders
     */
    public function __construct(
        public Expression $table,
        public array $values,
        public array $wheres,
        public array $orders,
        public ?int $limit,
    ) {}
}
