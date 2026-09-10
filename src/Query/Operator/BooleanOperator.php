<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Operator;

enum BooleanOperator: string
{
    case And = 'AND';
    case Or = 'OR';
}
