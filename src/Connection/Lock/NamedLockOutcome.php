<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Lock;

enum NamedLockOutcome: string
{
    case Granted = 'granted';
    case Contended = 'contended';
    case Failed = 'failed';
}
