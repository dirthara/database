<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use RuntimeException;
use Dirthara\Database\Query\Clause\Lock;

use function sprintf;

final class RowLockException extends RuntimeException implements DatabaseException
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

    public static function outsideTransaction(string $connection, Lock $lock): self
    {
        return new self(
            message: sprintf(
                'A query with a pessimistic row lock must run inside a transaction on connection "%s", or the lock ends '
                . 'with the statement.',
                self::printable($connection),
            ),
            context: [
                'connection' => $connection,
                'lock_mode' => $lock->mode->value,
                'lock_wait' => $lock->wait->value,
            ],
        );
    }
}
