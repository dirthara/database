<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Operator\BooleanOperator;

final readonly class NestedWhere implements WhereClause
{
    /**
     * @param list<WhereClause> $wheres
     */
    public function __construct(
        public array $wheres,
        public BooleanOperator $boolean,
    ) {}
}
