<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use RuntimeException;

use function sprintf;

final class UnsupportedQueryException extends RuntimeException implements DatabaseException
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

    public static function joinedMutation(string $operation): self
    {
        return new self(message: sprintf('Joined %s queries are not supported.', $operation), context: [
            'operation' => $operation,
        ]);
    }

    public static function fullJoin(string $database): self
    {
        return new self(message: sprintf('%s does not support a full join.', $database), context: [
            'database' => $database,
        ]);
    }

    public static function orderedMutation(string $operation): self
    {
        return new self(message: sprintf('An ordered %s query is not supported by this driver.', $operation), context: [
            'operation' => $operation,
        ]);
    }

    public static function limitedMutation(string $operation): self
    {
        return new self(message: sprintf('A limited %s query is not supported by this driver.', $operation), context: [
            'operation' => $operation,
        ]);
    }

    public static function unknownWhere(string $class): self
    {
        return new self(message: sprintf('The where clause "%s" is not supported by this grammar.', $class), context: [
            'clause' => $class,
        ]);
    }

    public static function unknownExpression(string $class): self
    {
        return new self(message: sprintf('The expression "%s" is not supported by this grammar.', $class), context: [
            'expression' => $class,
        ]);
    }
}
