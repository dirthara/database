<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Lock;

use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

interface NamedLockGrammar
{
    public function resource(ConnectionConfig $config, string $name): int|string;

    public function acquire(): string;

    public function tryAcquire(): string;

    public function release(): string;

    public function outcome(?int $value): NamedLockOutcome;
}
