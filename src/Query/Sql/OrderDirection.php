<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Sql;

enum OrderDirection: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
