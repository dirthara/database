<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Queries;

final readonly class InsertQuery
{
    /**
     * @param array<string, mixed>|list<array<string, mixed>> $rows
     */
    public function __construct(
        public string $table,
        public array $rows,
    ) {}
}
