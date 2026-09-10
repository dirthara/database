<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Expression\Expression;

final readonly class OrderBy
{
    public function __construct(
        public Expression $column,
        public OrderDirection $direction,
    ) {}
}
