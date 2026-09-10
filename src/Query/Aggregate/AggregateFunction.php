<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Aggregate;

enum AggregateFunction: string
{
    case Count = 'COUNT';
    case Sum = 'SUM';
    case Average = 'AVG';
    case Minimum = 'MIN';
    case Maximum = 'MAX';
}
