<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Sql\BooleanOperator;

/**
 * @internal
 */
interface WhereClause
{
    /**
     * How this condition joins to the one before it.
     */
    public BooleanOperator $boolean { get; }
}
