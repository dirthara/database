<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Queries;

use Dirthara\Database\Query\Clause\WhereClause;

final readonly class DeleteQuery
{
    /**
     * @param array<WhereClause> $wheres
     */
    public function __construct(
        public string $table,
        public array $wheres,
        public ?int $limit,
    ) {}
}
