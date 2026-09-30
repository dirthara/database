<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use InvalidArgumentException;

use function implode;
use function sprintf;

final class InvalidExpressionException extends InvalidArgumentException implements DatabaseException
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

    public static function emptyIdentifier(): self
    {
        return new self(message: 'An identifier cannot be empty.', context: []);
    }

    public static function emptySegment(string $identifier): self
    {
        return new self(
            message: sprintf('The identifier "%s" has an empty segment.', self::printable($identifier)),
            context: ['identifier' => $identifier],
        );
    }

    public static function sqlInIdentifier(string $identifier): self
    {
        return new self(
            message: sprintf(
                'The identifier "%s" looks like SQL rather than a name; use a raw expression instead.',
                self::printable($identifier),
            ),
            context: ['identifier' => $identifier],
        );
    }

    public static function emptyAlias(): self
    {
        return new self(message: 'An alias cannot be empty.', context: []);
    }

    public static function multipleAliases(string $expression): self
    {
        return new self(
            message: sprintf('The expression "%s" has more than one alias.', self::printable($expression)),
            context: ['expression' => $expression],
        );
    }

    /**
     * @param list<string> $expected
     */
    public static function unknownOperator(string $operator, array $expected): self
    {
        return new self(
            message: sprintf(
                'The operator "%s" cannot compare two values; expected one of %s.',
                self::printable($operator),
                implode(', ', $expected),
            ),
            context: ['operator' => $operator, 'expected' => $expected],
        );
    }
}
