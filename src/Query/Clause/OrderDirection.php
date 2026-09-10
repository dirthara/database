<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

enum OrderDirection: string
{
    case Ascending = 'ASC';
    case Descending = 'DESC';
}
