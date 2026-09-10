<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use function sprintf;

final class SQLiteQueryGrammar extends SqlQueryGrammar
{
    /**
     * The row count SQLite reads as no limit, for an offset that has no limit of its own.
     */
    private const int UNLIMITED = -1;

    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '"');
    }

    protected function compileLimit(?int $limit, ?int $offset): string
    {
        if ($offset === null) {
            return $limit === null ? '' : sprintf(' LIMIT %d', $limit);
        }

        return sprintf(' LIMIT %d OFFSET %d', $limit ?? self::UNLIMITED, $offset);
    }
}
