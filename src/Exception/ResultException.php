<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use RuntimeException;
use Dirthara\Database\Connection\Operation;

use function is_int;
use function sprintf;

final class ResultException extends RuntimeException implements DatabaseException
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

    /**
     * @param array<int, string> $columns
     */
    public static function unknownColumn(int|string $column, array $columns): self
    {
        return new self(
            message: sprintf(
                'The result set has no column %s.',
                is_int($column) ? '#' . $column : sprintf('"%s"', self::printable($column)),
            ),
            context: [
                'operation' => Operation::Column->value,
                'column' => $column,
                'columns' => $columns,
            ],
        );
    }

    public static function columnMetadataUnavailable(string $column): self
    {
        return new self(
            message: sprintf(
                'The database driver does not expose column metadata, so column "%s" cannot be selected by name.',
                self::printable($column),
            ),
            context: [
                'operation' => Operation::Column->value,
                'column' => $column,
            ],
        );
    }
}
