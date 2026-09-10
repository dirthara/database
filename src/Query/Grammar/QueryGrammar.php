<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Aggregate\AggregateFunction;

interface QueryGrammar
{
    public function compileSelect(SelectQuery $query): CompiledQuery;

    public function compileExists(SelectQuery $query): CompiledQuery;

    public function compileAggregate(
        SelectQuery $query,
        AggregateFunction $function,
        Expression $column,
    ): CompiledQuery;

    public function compileInsert(InsertQuery $query): CompiledQuery;

    public function compileUpdate(UpdateQuery $query): CompiledQuery;

    public function compileDelete(DeleteQuery $query): CompiledQuery;
}
