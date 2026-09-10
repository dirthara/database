<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Queries\CompiledQuery;

use function sprintf;

final class SqlServerQueryGrammar extends SqlQueryGrammar
{
    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '[', ']');
    }

    public function compileInsertReturning(InsertQuery $query, Identifier $key): ?CompiledQuery
    {
        $bindings = [];
        $returning = sprintf(' OUTPUT INSERTED.%s', $this->wrapIdentifier($key));

        return new CompiledQuery($this->insertSql($query, $bindings, $returning), $bindings);
    }

    protected function compileTop(SelectQuery $query): string
    {
        if ($query->limit === null || $query->offset !== null || $query->unions !== []) {
            return '';
        }

        return sprintf('TOP (%d) ', $query->limit);
    }

    protected function compileOrders(SelectQuery $query, array &$bindings): string
    {
        if ($query->orders === [] && $this->paged($query)) {
            // A compound orders by an output column, so a union pages by the first one.
            return $query->unions === [] ? ' ORDER BY (SELECT NULL)' : ' ORDER BY 1';
        }

        return parent::compileOrders($query, $bindings);
    }

    protected function compileLimit(SelectQuery $query): string
    {
        if (!$this->paged($query)) {
            return '';
        }

        if ($query->limit === null) {
            return sprintf(' OFFSET %d ROWS', $query->offset ?? 0);
        }

        return sprintf(' OFFSET %d ROWS FETCH NEXT %d ROWS ONLY', $query->offset ?? 0, $query->limit);
    }

    private function paged(SelectQuery $query): bool
    {
        return $query->offset !== null || $query->unions !== [] && $query->limit !== null;
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
