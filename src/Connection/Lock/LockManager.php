<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Lock;

use Dirthara\Database\Exception\NamedLockException;
use Dirthara\Database\Exception\InvalidLockNameException;
use Dirthara\Database\Exception\UnsupportedLockException;

interface LockManager
{
    /**
     * @throws NamedLockException
     * @throws InvalidLockNameException
     * @throws UnsupportedLockException
     */
    public function acquire(string $name): AcquiredLock;

    /**
     * @throws NamedLockException
     * @throws InvalidLockNameException
     * @throws UnsupportedLockException
     */
    public function tryAcquire(string $name): ?AcquiredLock;

    /**
     * @return list<string>
     */
    public function held(): array;
}
