<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Sql;

enum JoinType: string
{
    case Inner = 'INNER JOIN';
    case Left = 'LEFT JOIN';
    case Right = 'RIGHT JOIN';
    case Full = 'FULL JOIN';
}
