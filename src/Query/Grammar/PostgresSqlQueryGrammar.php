<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Queries\CompiledQuery;

use function sprintf;

class PostgresSqlQueryGrammar extends SqlQueryGrammar
{
    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '"');
    }

    public function compileInsertReturning(InsertQuery $query, Identifier $key): ?CompiledQuery
    {
        $bindings = [];
        $sql = $this->insertSql($query, $bindings, '');

        return new CompiledQuery(sprintf('%s RETURNING %s', $sql, $this->wrapIdentifier($key)), $bindings);
    }
}
