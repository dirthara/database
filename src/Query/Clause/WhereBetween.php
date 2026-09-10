<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Sql\BooleanOperator;
use Dirthara\Database\Query\Expression\Expression;

/**
 * @internal
 */
final readonly class WhereBetween implements WhereClause
{
    public function __construct(
        public Expression $column,
        public mixed $from,
        public mixed $to,
        public bool $negated,
        public BooleanOperator $boolean,
    ) {}
}
