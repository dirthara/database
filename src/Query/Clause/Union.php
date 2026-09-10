<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Queries\SelectQuery;

final readonly class Union
{
    public function __construct(
        public SelectQuery $query,
        public bool $all,
    ) {}
}
