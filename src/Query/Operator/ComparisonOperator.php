<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Operator;

enum ComparisonOperator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';
    case In = 'IN';
    case NotIn = 'NOT IN';
    case IsNull = 'IS NULL';
    case IsNotNull = 'IS NOT NULL';
    case Between = 'BETWEEN';
    case NotBetween = 'NOT BETWEEN';

    public function isEquality(): bool
    {
        return $this == self::Equal;
    }

    public function isInequality(): bool
    {
        return $this == self::NotEqual;
    }
}
