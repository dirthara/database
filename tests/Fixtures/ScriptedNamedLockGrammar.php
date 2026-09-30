<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Fixtures;

use Dirthara\Database\Connection\Lock\NamedLockGrammar;
use Dirthara\Database\Connection\Lock\NamedLockOutcome;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

final class ScriptedNamedLockGrammar implements NamedLockGrammar
{
    public const string GRANTED = 'SELECT 1 AS outcome WHERE ? IS NOT NULL';

    public const string CONTENDED = 'SELECT 0 AS outcome WHERE ? IS NOT NULL';

    public const string FAILED = 'SELECT NULL AS outcome WHERE ? IS NOT NULL';

    public const string NO_ROW = 'SELECT 1 AS outcome WHERE ? IS NULL';

    /**
     * @var list<string>
     */
    public array $statements = [];

    public function __construct(
        private readonly string $acquire = self::GRANTED,
        private readonly string $tryAcquire = self::GRANTED,
        private readonly string $release = self::GRANTED,
    ) {}

    public function resource(ConnectionConfig $config, string $name): string
    {
        return 'resource:' . $name;
    }

    public function acquire(): string
    {
        return $this->statements[] = $this->acquire;
    }

    public function tryAcquire(): string
    {
        return $this->statements[] = $this->tryAcquire;
    }

    public function release(): string
    {
        return $this->statements[] = $this->release;
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
