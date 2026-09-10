<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Join;

use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Operator\ComparisonOperator;

final readonly class JoinClause
{
    public function __construct(
        public string $table,
        public Expression $first,
        public ComparisonOperator $operator,
        public Expression $second,
        public JoinType $type,
    ) {}
}
