<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Queries;

final readonly class CompiledQuery
{
    /**
     * @param list<array-key, scalar> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings = [],
    ) {}
}
