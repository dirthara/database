<?php

declare(strict_types=1);

namespace Dirthara\Database\Query;

use Closure;
use LogicException;
use InvalidArgumentException;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Join\JoinType;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Clause\WhereIn;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Query\Join\JoinClause;
use Dirthara\Database\Query\Clause\WhereNull;
use Dirthara\Database\Query\Clause\NestedWhere;
use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Clause\WhereColumn;
use Dirthara\Database\Query\Clause\WhereExists;
use Dirthara\Database\Query\Clause\WhereBetween;
use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Query\Clause\OrderDirection;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Operator\BooleanOperator;
use Dirthara\Database\Query\Operator\ComparisonOperator;
use Dirthara\Database\Connection\Exceptions\QueryException;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

final class QueryBuilder
{
    /**
     * @var list<Expression>
     */
    private array $columns = [];

    /**
     * @var list<JoinClause>
     */
    private array $joins = [];

    /**
     * @var list<WhereClause>
     */
    private array $wheres = [];

    /**
     * @var list<Expression>
     */
    private array $groups = [];

    /**
     * @var list<WhereClause>
     */
    private array $havings = [];

    /**
     * @var list<OrderBy>
     */
    private array $orders = [];

    private ?int $limit = null;

    private ?int $offset = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly QueryGrammar $grammar,
        private readonly string $table,
    ) {
        if (trim($this->table) === '') {
            throw new InvalidArgumentException('A query table cannot be empty.');
        }
    }

    public function select(string|Expression ...$columns): self
    {
        $this->columns = array_values(array_map(static fn($column) => is_string($column)
            ? new Expression($column)
            : $column, $columns));

        return $this;
    }

    public function addSelect(string|Expression ...$columns): self
    {
        array_push($this->columns, ...array_map(static fn($column) => is_string($column)
            ? new Expression($column)
            : $column, $columns));

        return $this;
    }

    public function where(string|Expression $column, string|ComparisonOperator $operator, mixed $value = null): self
    {
        return $this->addBasicWhere(column: $column, operator: $operator, value: $value, boolean: BooleanOperator::And);
    }

    public function orWhere(string|Expression $column, string|ComparisonOperator $operator, mixed $value = null): self
    {
        return $this->addBasicWhere(column: $column, operator: $operator, value: $value, boolean: BooleanOperator::Or);
    }

    public function whereNull(string|Expression $column): self
    {
        $this->wheres[] = new WhereNull(
            column: is_string($column) ? new Expression($column) : $column,
            negated: false,
            boolean: BooleanOperator::And,
        );

        return $this;
    }

    public function orWhereNull(string|Expression $column): self
    {
        $this->wheres[] = new WhereNull(
            column: is_string($column) ? new Expression($column) : $column,
            negated: false,
            boolean: BooleanOperator::Or,
        );

        return $this;
    }

    public function whereNotNull(string|Expression $column): self
    {
        $this->wheres[] = new WhereNull(
            column: is_string($column) ? new Expression($column) : $column,
            negated: true,
            boolean: BooleanOperator::And,
        );

        return $this;
    }

    public function orWhereNotNull(string|Expression $column): self
    {
        $this->wheres[] = new WhereNull(
            column: is_string($column) ? new Expression($column) : $column,
            negated: true,
            boolean: BooleanOperator::Or,
        );

        return $this;
    }

    /**
     * @param iterable<scalar> $values
     */
    public function whereIn(string|Expression $column, iterable $values): self
    {
        return $this->addWhereIn($column, $values, false, BooleanOperator::And);
    }

    /**
     * @param iterable<scalar> $values
     */
    public function orWhereIn(string|Expression $column, iterable $values): self
    {
        return $this->addWhereIn($column, $values, false, BooleanOperator::Or);
    }

    /**
     * @param iterable<scalar> $values
     */
    public function whereNotIn(string|Expression $column, iterable $values): self
    {
        return $this->addWhereIn($column, $values, true, BooleanOperator::And);
    }

    /**
     * @param iterable<scalar> $values
     */
    public function orWhereNotIn(string|Expression $column, iterable $values): self
    {
        return $this->addWhereIn($column, $values, true, BooleanOperator::Or);
    }

    public function whereBetween(string|Expression $column, mixed $from, mixed $to): self
    {
        $this->wheres[] = new WhereBetween(
            column: is_string($column) ? new Expression($column) : $column,
            from: $from,
            to: $to,
            negated: false,
            boolean: BooleanOperator::And,
        );

        return $this;
    }

    public function whereNotBetween(string|Expression $column, mixed $from, mixed $to): self
    {
        $this->wheres[] = new WhereBetween(
            column: is_string($column) ? new Expression($column) : $column,
            from: $from,
            to: $to,
            negated: true,
            boolean: BooleanOperator::And,
        );

        return $this;
    }

    public function whereColumn(
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
    ): self {
        $this->wheres[] = new WhereColumn(
            first: is_string($first) ? new Expression($first) : $first,
            operator: is_string($operator) ? ComparisonOperator::from(strtoupper($operator)) : $operator,
            second: is_string($second) ? new Expression($second) : $second,
            boolean: BooleanOperator::And,
        );

        return $this;
    }

    public function orWhereColumn(
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
    ): self {
        $this->wheres[] = new WhereColumn(
            first: is_string($first) ? new Expression($first) : $first,
            operator: is_string($operator) ? ComparisonOperator::from(strtoupper($operator)) : $operator,
            second: is_string($second) ? new Expression($second) : $second,
            boolean: BooleanOperator::Or,
        );

        return $this;
    }

    public function whereNested(Closure $callback): self
    {
        return $this->addNestedWhere(BooleanOperator::And, $callback);
    }

    public function orWhereNested(Closure $callback): self
    {
        return $this->addNestedWhere(BooleanOperator::Or, $callback);
    }

    public function whereExists(self $query): self
    {
        $this->wheres[] = new WhereExists(
            query: $query->toSelectQuery(),
            negated: false,
            boolean: BooleanOperator::And,
        );

        return $this;
    }

    public function whereNotExists(self $query): self
    {
        $this->wheres[] = new WhereExists(query: $query->toSelectQuery(), negated: true, boolean: BooleanOperator::And);

        return $this;
    }

    public function join(
        string $table, // todo Also allow Expression?
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
        JoinType $type = JoinType::Inner,
    ): self {
        $this->joins[] = new JoinClause(
            table: $table,
            first: is_string($first) ? new Expression($first) : $first,
            operator: is_string($operator) ? ComparisonOperator::from(strtoupper($operator)) : $operator,
            second: is_string($second) ? new Expression($second) : $second,
            type: $type,
        );

        return $this;
    }

    public function leftJoin(
        string $table, // todo Also allow Expression?
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
    ): self {
        return $this->join(
            table: $table,
            first: $first,
            operator: is_string($operator) ? ComparisonOperator::from(strtoupper($operator)) : $operator,
            second: $second,
            type: JoinType::Left,
        );
    }

    public function rightJoin(
        string $table, // todo Also allow Expression?
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
    ): self {
        return $this->join(
            table: $table,
            first: $first,
            operator: is_string($operator) ? ComparisonOperator::from(strtoupper($operator)) : $operator,
            second: $second,
            type: JoinType::Right,
        );
    }

    public function groupBy(string|Expression ...$columns): self
    {
        array_push($this->groups, ...array_map(static fn($column) => is_string($column)
            ? new Expression($column)
            : $column, $columns));

        return $this;
    }

    public function having(string|Expression $column, string|ComparisonOperator $operator, mixed $value = null): self // todo Is it logical to allow null here?
    {
        return $this->addHaving(
            column: is_string($column) ? new Expression($column) : $column,
            operator: is_string($operator) ? ComparisonOperator::from(strtoupper($operator)) : $operator,
            value: $value,
            boolean: BooleanOperator::And,
        );
    }

    public function orHaving(string|Expression $column, string|ComparisonOperator $operator, mixed $value = null): self // todo Is it logical to allow null here?
    {
        return $this->addHaving(
            column: is_string($column) ? new Expression($column) : $column,
            operator: is_string($operator) ? ComparisonOperator::from(strtoupper($operator)) : $operator,
            value: $value,
            boolean: BooleanOperator::Or,
        );
    }

    public function orderBy(string|Expression $column, OrderDirection $direction = OrderDirection::Ascending): self
    {
        $this->orders[] = new OrderBy(
            column: is_string($column) ? new Expression($column) : $column,
            direction: $direction,
        );

        return $this;
    }

    public function orderByDesc(string|Expression $column): self
    {
        return $this->orderBy($column, OrderDirection::Descending);
    }

    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new InvalidArgumentException('Query limit cannot be negative.');
        }

        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new InvalidArgumentException('Query offset cannot be negative.');
        }

        $this->offset = $offset;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function get(): array
    {
        $query = $this->grammar->compileSelect($this->toSelectQuery());

        return $this->connection->execute($query->sql, $query->bindings)->all();
    }

    /**
     * @return iterable<array<string, mixed>>
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function cursor(): iterable
    {
        $query = $this->grammar->compileSelect($this->toSelectQuery());

        yield from $this->connection->execute($query->sql, $query->bindings)->iterate();
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function first(): ?array
    {
        $query = clone $this;

        $query->limit(1);

        $compiled = $this->grammar->compileSelect($query->toSelectQuery());

        return $this->connection->execute($compiled->sql, $compiled->bindings)->first();
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function exists(): bool
    {
        $query = $this->grammar->compileExists($this->toSelectQuery());

        $row = $this->connection->execute($query->sql, $query->bindings)->first();

        if ($row === null) {
            return false;
        }

        return (bool) reset($row);
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function count(string|Expression $column = '*'): int
    {
        $query = $this->grammar->compileCount(
            $this->toSelectQuery(),
            is_string($column) ? new Expression($column) : $column,
        );

        $row = $this->connection->execute($query->sql, $query->bindings)->first();

        if ($row === null) {
            return 0;
        }

        return (int) reset($row);
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $values
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function insert(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $rows = $this->normaliseInsertRows($values);

        $query = $this->grammar->compileInsert(new InsertQuery(table: $this->table, rows: $rows));

        return $this->connection->execute($query->sql, $query->bindings)->affectedRows();
    }

    /**
     * @param array<string, mixed> $values
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function update(array $values): int
    {
        if ($values === []) {
            return 0;
        }

        $this->assertNoJoinedMutation('update');

        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: $this->table,
            values: $values,
            wheres: $this->wheres,
            limit: $this->limit,
        ));

        return $this->connection->execute($query->sql, $query->bindings)->affectedRows();
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function delete(): int
    {
        $this->assertNoJoinedMutation('delete');

        $query = $this->grammar->compileDelete(new DeleteQuery(
            table: $this->table,
            wheres: $this->wheres,
            limit: $this->limit,
        ));

        return $this->connection->execute($query->sql, $query->bindings)->affectedRows();
    }

    public function toSql(): string
    {
        return $this->compile()->sql;
    }

    /**
     * @return list<mixed>
     */
    public function bindings(): array
    {
        return $this->compile()->bindings;
    }

    public function compile(): CompiledQuery
    {
        return $this->grammar->compileSelect($this->toSelectQuery());
    }

    public function toSelectQuery(): SelectQuery
    {
        return new SelectQuery(
            table: $this->table,
            columns: $this->columns === [] ? [new Expression('*')] : $this->columns,
            joins: $this->joins,
            wheres: $this->wheres,
            groups: $this->groups,
            havings: $this->havings,
            orders: $this->orders,
            limit: $this->limit,
            offset: $this->offset,
        );
    }

    private function addBasicWhere(
        string|Expression $column,
        string|ComparisonOperator $operator,
        mixed $value,
        BooleanOperator $boolean,
    ): self {
        $operator = is_string($operator) ? ComparisonOperator::from(strtoupper($operator)) : $operator;

        if ($value === null) {
            if ($operator->isEquality()) {
                $this->wheres[] = new WhereNull(
                    column: is_string($column) ? new Expression($column) : $column,
                    negated: false,
                    boolean: $boolean,
                );

                return $this;
            }

            if ($operator->isInequality()) {
                $this->wheres[] = new WhereNull(
                    column: is_string($column) ? new Expression($column) : $column,
                    negated: true,
                    boolean: $boolean,
                );

                return $this;
            }

            throw new InvalidArgumentException(sprintf(
                'Operator [%s (%s)] cannot be used with NULL.',
                $operator->name,
                $operator->value,
            ));
        }

        $this->wheres[] = new Where(
            column: is_string($column) ? new Expression($column) : $column,
            operator: $operator,
            value: $value,
            boolean: $boolean,
        );

        return $this;
    }

    /**
     * @param iterable<scalar> $values
     */
    private function addWhereIn(
        string|Expression $column,
        iterable $values,
        bool $negated,
        BooleanOperator $boolean,
    ): self {
        $this->wheres[] = new WhereIn(
            column: is_string($column) ? new Expression($column) : $column,
            values: is_array($values) ? array_values($values) : array_values(iterator_to_array($values)),
            negated: $negated,
            boolean: $boolean,
        );

        return $this;
    }

    private function addNestedWhere(BooleanOperator $boolean, Closure $callback): self
    {
        $nested = new self(connection: $this->connection, grammar: $this->grammar, table: $this->table);

        $callback($nested);

        if ($nested->wheres === []) {
            return $this;
        }

        $this->wheres[] = new NestedWhere(wheres: $nested->wheres, boolean: $boolean);

        return $this;
    }

    private function addHaving(
        string|Expression $column,
        ComparisonOperator $operator,
        mixed $value,
        BooleanOperator $boolean,
    ): self {
        $this->havings[] = new Where(
            column: is_string($column) ? new Expression($column) : $column,
            operator: $operator,
            value: $value,
            boolean: $boolean,
        );

        return $this;
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $values
     *
     * @return list<array<string, mixed>>
     *
     * @throws InvalidArgumentException
     */
    private function normaliseInsertRows(array $values): array
    {
        if (array_is_list($values)) {
            foreach ($values as $row) {
                if (!is_array($row)) {
                    throw new InvalidArgumentException('Bulk inserts must contain arrays of column values.');
                }
            }

            /** @var list<array<string, mixed>> $values */
            return $values;
        }

        /** @var array<string, mixed> $values */
        return [$values];
    }

    /**
     * @throws LogicException
     */
    private function assertNoJoinedMutation(string $operation): void
    {
        if ($this->joins === []) {
            return;
        }

        throw new LogicException(sprintf('Joined %s queries are not supported yet.', $operation));
    }
}
