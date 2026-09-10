<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Sql\JoinType;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Sql\ComparisonOperator;

/**
 * @internal
 */
final readonly class JoinClause
{
    public function __construct(
        public Expression $table,
        public Expression $first,
        public ComparisonOperator $operator,
        public Expression $second,
        public JoinType $type,
    ) {}
}
