<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Clause;

use Dirthara\Database\Query\Sql\LockMode;
use Dirthara\Database\Query\Sql\LockWait;

/**
 * @internal
 */
final readonly class Lock
{
    public function __construct(
        public LockMode $mode,
        public LockWait $wait,
    ) {}
}
