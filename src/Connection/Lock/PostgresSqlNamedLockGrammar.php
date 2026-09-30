<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Lock;

use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function hash;
use function substr;
use function unpack;

final readonly class PostgresSqlNamedLockGrammar implements NamedLockGrammar
{
    public function resource(ConnectionConfig $config, string $name): int
    {
        /** @var array{1: int} $key */
        $key = unpack('J', substr(hash('sha256', $name, binary: true), offset: 0, length: 8));

        return $key[1];
    }

    public function acquire(): string
    {
        return 'SELECT 1 AS outcome FROM pg_advisory_lock(CAST(? AS bigint))';
    }

    public function tryAcquire(): string
    {
        return 'SELECT CASE WHEN pg_try_advisory_lock(CAST(? AS bigint)) THEN 1 ELSE 0 END AS outcome';
    }

    public function release(): string
    {
        return 'SELECT CASE WHEN pg_advisory_unlock(CAST(? AS bigint)) THEN 1 ELSE 0 END AS outcome';
    }

    public function outcome(?int $value): NamedLockOutcome
    {
        return match ($value) {
            1 => NamedLockOutcome::Granted,
            0 => NamedLockOutcome::Contended,
            default => NamedLockOutcome::Failed,
        };
    }
}
