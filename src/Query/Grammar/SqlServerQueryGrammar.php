<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Queries\CompiledQuery;

class SqlServerQueryGrammar implements QueryGrammar
{
    public function compileSelect(SelectQuery $query): CompiledQuery
    {
        // TODO: Implement compileSelect() method.
    }

    public function compileExists(SelectQuery $query): CompiledQuery
    {
        // TODO: Implement compileExists() method.
    }

    public function compileCount(SelectQuery $query, Expression $column): CompiledQuery
    {
        // TODO: Implement compileCount() method.
    }

    public function compileInsert(InsertQuery $query): CompiledQuery
    {
        // TODO: Implement compileInsert() method.
    }

    public function compileUpdate(UpdateQuery $query): CompiledQuery
    {
        // TODO: Implement compileUpdate() method.
    }

    public function compileDelete(DeleteQuery $query): CompiledQuery
    {
        // TODO: Implement compileDelete() method.
    }
}
