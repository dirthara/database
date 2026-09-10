<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Sql\BooleanOperator;
use Dirthara\Database\Query\Expression\Expression;

/**
 * @internal
 */
final readonly class WhereIn implements WhereClause
{
    /**
     * @param list<scalar> $values
     */
    public function __construct(
        public Expression $column,
        public array $values,
        public bool $negated,
        public BooleanOperator $boolean,
    ) {}
}
