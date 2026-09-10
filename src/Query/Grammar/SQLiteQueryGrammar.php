<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use Dirthara\Database\Query\Queries\SelectQuery;

use function sprintf;

class SQLiteQueryGrammar extends SqlQueryGrammar
{
    /**
     * The row count SQLite reads as no limit, for an offset that has no limit of its own.
     */
    private const int UNLIMITED = -1;

    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '"');
    }

    protected function compileLimit(SelectQuery $query): string
    {
        if ($query->offset === null) {
            return $query->limit === null ? '' : sprintf(' LIMIT %d', $query->limit);
        }

        return sprintf(' LIMIT %d OFFSET %d', $query->limit ?? self::UNLIMITED, $query->offset);
    }
}
