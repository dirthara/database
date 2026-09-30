<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use RuntimeException;
use Dirthara\Database\Query\Clause\Lock;

use function sprintf;

final class UnsupportedLockException extends RuntimeException implements DatabaseException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(string $message, array $context)
    {
        parent::__construct($message);

        $this->context = $context;
    }

    public static function rowLocksUnsupported(string $database, Lock $lock): self
    {
        return new self(message: sprintf('%s does not support pessimistic row locks.', $database), context: [
            'database' => $database,
            ...self::describe($lock),
        ]);
    }

    public static function lockedShape(string $construct, Lock $lock): self
    {
        return new self(
            message: sprintf('A pessimistic row lock cannot be applied to a query with %s.', $construct),
            context: ['construct' => $construct, ...self::describe($lock)],
        );
    }

    public static function lockedOperation(string $operation, Lock $lock): self
    {
        return new self(
            message: sprintf(
                'A query with a pessimistic row lock cannot run %s; lock rows with get(), first(), or cursor().',
                $operation,
            ),
            context: ['operation' => $operation, ...self::describe($lock)],
        );
    }

    public static function nestedLock(Lock $lock): self
    {
        return new self(
            message: 'A pessimistic row lock cannot be applied to a subquery or a union operand; lock the outer query instead.',
            context: self::describe($lock),
        );
    }

    /**
     * @return array<string, string>
     */
    private static function describe(Lock $lock): array
    {
        return ['lock_mode' => $lock->mode->value, 'lock_wait' => $lock->wait->value];
    }
}
