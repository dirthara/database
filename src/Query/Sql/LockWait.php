<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Sql;

enum LockWait: string
{
    case Wait = 'wait';
    case NoWait = 'no_wait';
    case SkipLocked = 'skip_locked';
}
