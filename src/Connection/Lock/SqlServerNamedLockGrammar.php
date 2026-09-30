<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Lock;

use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function hash;

final readonly class SqlServerNamedLockGrammar implements NamedLockGrammar
{
    public function resource(ConnectionConfig $config, string $name): string
    {
        return 'dirthara:' . hash('sha256', $name);
    }

    public function acquire(): string
    {
        return $this->getAppLock(-1);
    }

    public function tryAcquire(): string
    {
        return $this->getAppLock(0);
    }

    public function release(): string
    {
        return (
            'SET NOCOUNT ON; DECLARE @outcome int; '
            . "EXEC @outcome = sp_releaseapplock @Resource = ?, @LockOwner = 'Session'; "
            . 'SELECT @outcome AS outcome;'
        );
    }

    public function outcome(?int $value): NamedLockOutcome
    {
        return match ($value) {
            0, 1 => NamedLockOutcome::Granted,
            -1 => NamedLockOutcome::Contended,
            default => NamedLockOutcome::Failed,
        };
    }

    private function getAppLock(int $timeout): string
    {
        return (
            'SET NOCOUNT ON; DECLARE @outcome int; '
            . "EXEC @outcome = sp_getapplock @Resource = ?, @LockMode = 'Exclusive', @LockOwner = 'Session', "
            . '@LockTimeout = '
            . $timeout
            . '; '
            . 'SELECT @outcome AS outcome;'
        );
    }
}
