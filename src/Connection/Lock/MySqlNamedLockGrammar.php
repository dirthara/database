<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Lock;

use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function hash;
use function strlen;

final readonly class MySqlNamedLockGrammar implements NamedLockGrammar
{
    public function resource(ConnectionConfig $config, string $name): string
    {
        $database = $config->database ?? '';

        return hash('sha256', strlen($database) . ':' . $database . $name);
    }

    public function acquire(): string
    {
        return 'SELECT GET_LOCK(?, -1) AS outcome';
    }

    public function tryAcquire(): string
    {
        return 'SELECT GET_LOCK(?, 0) AS outcome';
    }

    public function release(): string
    {
        return 'SELECT RELEASE_LOCK(?) AS outcome';
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
