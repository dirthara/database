<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use InvalidArgumentException;

final class InvalidLockNameException extends InvalidArgumentException implements DatabaseException
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

    public static function empty(): self
    {
        return new self(message: 'A named lock needs a nonempty name.', context: []);
    }
}
