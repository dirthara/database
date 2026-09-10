<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Operator\BooleanOperator;

final readonly class WhereExists implements WhereClause
{
    public function __construct(
        public SelectQuery $query,
        public bool $negated,
        public BooleanOperator $boolean,
    ) {}
}
