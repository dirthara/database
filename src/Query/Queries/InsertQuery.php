<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Queries;

use Dirthara\Database\Query\Expression\Expression;

final readonly class InsertQuery
{
    /**
     * @param array<string, mixed>|list<array<string, mixed>> $rows
     */
    public function __construct(
        public Expression $table,
        public array $rows,
    ) {}
}
