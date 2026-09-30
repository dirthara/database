<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Sql;

enum LockMode: string
{
    case Update = 'update';
    case Share = 'share';
}
