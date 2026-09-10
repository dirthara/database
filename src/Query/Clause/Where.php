<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Sql\BooleanOperator;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Sql\ComparisonOperator;

/**
 * @internal
 */
final readonly class Where implements WhereClause
{
    public function __construct(
        public Expression $column,
        public ComparisonOperator $operator,
        public mixed $value,
        public BooleanOperator $boolean,
    ) {}
}
