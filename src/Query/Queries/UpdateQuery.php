<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Queries;

use Dirthara\Database\Query\Clause\WhereClause;

final readonly class UpdateQuery
{
    /**
     * @param array<string, mixed> $values
     * @param array<WhereClause> $wheres
     */
    public function __construct(
        public string $table,
        public array $values,
        public array $wheres,
        public ?int $limit,
    ) {}
}
