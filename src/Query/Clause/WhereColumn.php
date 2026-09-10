<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Sql\BooleanOperator;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Sql\ComparisonOperator;

/**
 * @internal
 */
final readonly class WhereColumn implements WhereClause
{
    public function __construct(
        public Expression $first,
        public ComparisonOperator $operator,
        public Expression $second,
        public BooleanOperator $boolean,
    ) {}
}
