<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Queries;

use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Expression\Expression;

final readonly class DeleteQuery
{
    /**
     * @param array<WhereClause> $wheres
     */
    public function __construct(
        public Expression $table,
        public array $wheres,
        public ?int $limit,
    ) {}
}
