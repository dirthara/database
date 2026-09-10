<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Sql;

/**
 * @internal
 */
enum BooleanOperator: string
{
    case And = 'AND';
    case Or = 'OR';
}
