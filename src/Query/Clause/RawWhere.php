<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Operator\BooleanOperator;

final readonly class RawWhere implements WhereClause
{
    /**
     * @param list<scalar|null> $bindings
     */
    public function __construct(
        public string $sql,
        public array $bindings,
        public BooleanOperator $boolean,
    ) {}
}
