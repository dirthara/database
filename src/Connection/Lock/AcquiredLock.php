<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Lock;

use Closure;
use Dirthara\Database\Exception\NamedLockException;

final class AcquiredLock
{
    public private(set) bool $released = false;

    /**
     * @internal
     *
     * @param Closure(): void $release
     */
    public function __construct(
        public readonly string $name,
        private readonly Closure $release,
    ) {}

    /**
     * @throws NamedLockException
     */
    public function release(): void
    {
        if ($this->released) {
            throw NamedLockException::alreadyReleased($this->name);
        }

        ($this->release)();

        $this->released = true;
    }
}
