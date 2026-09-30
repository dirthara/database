<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use InvalidArgumentException;

use function sprintf;

final class GrammarRegistryException extends InvalidArgumentException implements DatabaseException
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

    public static function alreadyRegistered(string $driver): self
    {
        return new self(
            message: sprintf('A query grammar is already registered for driver "%s".', self::printable($driver)),
            context: ['driver' => $driver],
        );
    }

    public static function notRegistered(string $driver): self
    {
        return new self(
            message: sprintf('No query grammar is registered for driver "%s".', self::printable($driver)),
            context: ['driver' => $driver],
        );
    }
}
