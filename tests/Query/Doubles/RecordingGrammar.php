<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Doubles;

use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Sql\AggregateFunction;

/**
 * A grammar that records the query it was handed and returns SQL the test chose.
 */
final class RecordingGrammar implements QueryGrammar
{
    public ?SelectQuery $select = null;

    public ?SelectQuery $exists = null;

    public ?SelectQuery $count = null;

    public ?Expression $countColumn = null;

    public ?AggregateFunction $aggregateFunction = null;

    public ?InsertQuery $insert = null;

    public ?UpdateQuery $update = null;

    public ?DeleteQuery $delete = null;

    public ?Identifier $insertKey = null;

    public ?CompiledQuery $returning = null;

    public function __construct(
        public CompiledQuery $result = new CompiledQuery('SELECT 1 AS one'),
    ) {}

    public function compileSelect(SelectQuery $query): CompiledQuery
    {
        $this->select = $query;

        return $this->result;
    }

    public function compileExists(SelectQuery $query): CompiledQuery
    {
        $this->exists = $query;

        return $this->result;
    }

    public function compileAggregate(SelectQuery $query, AggregateFunction $function, Expression $column): CompiledQuery
    {
        $this->count = $query;
        $this->countColumn = $column;
        $this->aggregateFunction = $function;

        return $this->result;
    }

    public function compileInsert(InsertQuery $query): CompiledQuery
    {
        $this->insert = $query;

        return $this->result;
    }

    public function compileInsertReturning(InsertQuery $query, Identifier $key): ?CompiledQuery
    {
        $this->insert = $query;
        $this->insertKey = $key;

        return $this->returning;
    }

    public function compileUpdate(UpdateQuery $query): CompiledQuery
    {
        $this->update = $query;

        return $this->result;
    }

    public function compileDelete(DeleteQuery $query): CompiledQuery
    {
        $this->delete = $query;

        return $this->result;
    }
}
