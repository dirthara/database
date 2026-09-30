<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Fixtures\Query;

use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Sql\BooleanOperator;

final readonly class UnsupportedWhere implements WhereClause
{
    public function __construct(
        public BooleanOperator $boolean = BooleanOperator::And,
    ) {}
}
