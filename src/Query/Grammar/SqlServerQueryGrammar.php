<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use Dirthara\Database\Query\Queries\SelectQuery;

use function sprintf;

final class SqlServerQueryGrammar extends SqlQueryGrammar
{
    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '[', ']');
    }

    protected function compileTop(SelectQuery $query): string
    {
        if ($query->limit === null || $query->offset !== null) {
            return '';
        }

        return sprintf('TOP (%d) ', $query->limit);
    }

    /**
     * SQL Server only accepts OFFSET after an ORDER BY, so a paged query without one orders by nothing.
     */
    protected function compileOrders(SelectQuery $query, array &$bindings): string
    {
        if ($query->orders === [] && $query->offset !== null) {
            return ' ORDER BY (SELECT NULL)';
        }

        return parent::compileOrders($query, $bindings);
    }

    protected function compileLimit(SelectQuery $query): string
    {
        if ($query->offset === null) {
            return '';
        }

        if ($query->limit === null) {
            return sprintf(' OFFSET %d ROWS', $query->offset);
        }

        return sprintf(' OFFSET %d ROWS FETCH NEXT %d ROWS ONLY', $query->offset, $query->limit);
    }

    /**
     * @return array{string, string}
     */
    protected function compileMutationLimit(?int $limit, string $operation): array
    {
        return $limit === null ? ['', ''] : [sprintf(' TOP (%d)', $limit), ''];
    }

    protected function wrapExists(string $select): string
    {
        return sprintf('SELECT CASE WHEN EXISTS(%s) THEN 1 ELSE 0 END AS %s', $select, $this->quote('exists'));
    }
}
