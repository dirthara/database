<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Operator\BooleanOperator;

final readonly class WhereIn implements WhereClause
{
    public function __construct(
        public Expression $column,
        public array $values,
        public bool $negated,
        public BooleanOperator $boolean,
    ) {}
}
