<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Exceptions;

use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Exceptions\DatabaseException;

use function is_int;
use function sprintf;

class ResultException extends DatabaseException
{
    /**
     * @param array<int, string> $columns
     */
    public static function unknownColumn(int|string $column, array $columns): self
    {
        return new self(
            message: sprintf(
                'The result set has no column %s.',
                is_int($column) ? '#' . $column : sprintf('"%s"', $column),
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
        return new self(message: 'The database driver does not expose column metadata, so a column cannot be selected by name.', context: [
            'operation' => Operation::Column->value,
            'column' => $column,
        ]);
    }
}
