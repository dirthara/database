<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Expression;

final readonly class RawExpression implements Expression
{
    /**
     * @param list<scalar|null> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings = [],
    ) {}
}
