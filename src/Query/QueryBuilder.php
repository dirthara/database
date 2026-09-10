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
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Expression\RawExpression;
use Dirthara\Database\Query\Operator\BooleanOperator;
use Dirthara\Database\Query\Operator\ComparisonOperator;
use Dirthara\Database\Query\Expression\ExpressionFactory;
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

    private readonly Expression $table;

    public function __construct(
        private readonly Connection $connection,
        private readonly QueryGrammar $grammar,
        string|Expression $table,
    ) {
        if (is_string($table) && trim($table) === '') {
            throw new InvalidArgumentException('A query table cannot be empty.');
        }

        $this->table = ExpressionFactory::from($table);
    }

    public function newQuery(string|Expression $table): self
    {
        return new self(connection: $this->connection, grammar: $this->grammar, table: $table);
    }

    public function select(string|Expression ...$columns): self
    {
        $this->columns = array_values(array_map(ExpressionFactory::from(...), $columns));

        return $this;
    }

    public function addSelect(string|Expression ...$columns): self
    {
        array_push($this->columns, ...array_map(ExpressionFactory::from(...), $columns));

        return $this;
    }

    /**
     * @param list<scalar|null> $bindings
     */
    public function selectRaw(string $sql, array $bindings = []): self
    {
        $this->columns[] = new RawExpression($sql, $bindings);

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
        return $this->addWhereNull($column, false, BooleanOperator::And);
    }

    public function orWhereNull(string|Expression $column): self
    {
        return $this->addWhereNull($column, false, BooleanOperator::Or);
    }

    public function whereNotNull(string|Expression $column): self
    {
        return $this->addWhereNull($column, true, BooleanOperator::And);
    }

    public function orWhereNotNull(string|Expression $column): self
    {
        return $this->addWhereNull($column, true, BooleanOperator::Or);
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
        return $this->addWhereBetween($column, $from, $to, false, BooleanOperator::And);
    }

    public function orWhereBetween(string|Expression $column, mixed $from, mixed $to): self
    {
        return $this->addWhereBetween($column, $from, $to, false, BooleanOperator::Or);
    }

    public function whereNotBetween(string|Expression $column, mixed $from, mixed $to): self
    {
        return $this->addWhereBetween($column, $from, $to, true, BooleanOperator::And);
    }

    public function orWhereNotBetween(string|Expression $column, mixed $from, mixed $to): self
    {
        return $this->addWhereBetween($column, $from, $to, true, BooleanOperator::Or);
    }

    public function whereColumn(
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
    ): self {
        return $this->addWhereColumn($first, $operator, $second, BooleanOperator::And);
    }

    public function orWhereColumn(
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
    ): self {
        return $this->addWhereColumn($first, $operator, $second, BooleanOperator::Or);
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
        return $this->addWhereExists($query, false, BooleanOperator::And);
    }

    public function orWhereExists(self $query): self
    {
        return $this->addWhereExists($query, false, BooleanOperator::Or);
    }

    public function whereNotExists(self $query): self
    {
        return $this->addWhereExists($query, true, BooleanOperator::And);
    }

    public function orWhereNotExists(self $query): self
    {
        return $this->addWhereExists($query, true, BooleanOperator::Or);
    }

    public function join(
        string|Expression $table,
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
        JoinType $type = JoinType::Inner,
    ): self {
        $this->joins[] = new JoinClause(
            table: ExpressionFactory::from($table),
            first: ExpressionFactory::from($first),
            operator: ComparisonOperator::parse($operator),
            second: ExpressionFactory::from($second),
            type: $type,
        );

        return $this;
    }

    public function leftJoin(
        string|Expression $table,
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
    ): self {
        return $this->join(
            table: $table,
            first: $first,
            operator: ComparisonOperator::parse($operator),
            second: $second,
            type: JoinType::Left,
        );
    }

    public function rightJoin(
        string|Expression $table,
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
    ): self {
        return $this->join(
            table: $table,
            first: $first,
            operator: ComparisonOperator::parse($operator),
            second: $second,
            type: JoinType::Right,
        );
    }

    public function groupBy(string|Expression ...$columns): self
    {
        array_push($this->groups, ...array_map(ExpressionFactory::from(...), $columns));

        return $this;
    }

    /**
     * @param list<scalar|null> $bindings
     */
    public function groupByRaw(string $sql, array $bindings = []): self
    {
        $this->groups[] = new RawExpression($sql, $bindings);

        return $this;
    }

    public function having(string|Expression $column, string|ComparisonOperator $operator, mixed $value = null): self // todo Is it logical to allow null here?
    {
        return $this->addHaving(
            column: ExpressionFactory::from($column),
            operator: ComparisonOperator::parse($operator),
            value: $value,
            boolean: BooleanOperator::And,
        );
    }

    public function orHaving(string|Expression $column, string|ComparisonOperator $operator, mixed $value = null): self // todo Is it logical to allow null here?
    {
        return $this->addHaving(
            column: ExpressionFactory::from($column),
            operator: ComparisonOperator::parse($operator),
            value: $value,
            boolean: BooleanOperator::Or,
        );
    }

    public function orderBy(string|Expression $column, OrderDirection $direction = OrderDirection::Ascending): self
    {
        $this->orders[] = new OrderBy(column: ExpressionFactory::from($column), direction: $direction);

        return $this;
    }

    /**
     * @param list<scalar|null> $bindings
     */
    public function orderByRaw(
        string $sql,
        array $bindings = [],
        OrderDirection $direction = OrderDirection::Ascending,
    ): self {
        $this->orders[] = new OrderBy(column: new RawExpression($sql, $bindings), direction: $direction);

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
        $query = $this->grammar->compileCount($this->toSelectQuery(), ExpressionFactory::from($column));

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

        $this->assertMutable('update');

        $query = $this->grammar->compileUpdate(new UpdateQuery(
            table: $this->table,
            values: $values,
            wheres: $this->wheres,
            orders: $this->orders,
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
        $this->assertMutable('delete');

        $query = $this->grammar->compileDelete(new DeleteQuery(
            table: $this->table,
            wheres: $this->wheres,
            orders: $this->orders,
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
            columns: $this->columns === [] ? [new Identifier('*')] : $this->columns,
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
        $operator = ComparisonOperator::parse($operator);

        if ($value === null) {
            if ($operator->isEquality()) {
                return $this->addWhereNull($column, false, $boolean);
            }

            if ($operator->isInequality()) {
                return $this->addWhereNull($column, true, $boolean);
            }

            throw new InvalidArgumentException(sprintf(
                'Operator [%s (%s)] cannot be used with NULL.',
                $operator->name,
                $operator->value,
            ));
        }

        $this->wheres[] = new Where(
            column: ExpressionFactory::from($column),
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
            column: ExpressionFactory::from($column),
            values: is_array($values) ? array_values($values) : array_values(iterator_to_array($values)),
            negated: $negated,
            boolean: $boolean,
        );

        return $this;
    }

    private function addWhereNull(string|Expression $column, bool $negated, BooleanOperator $boolean): self
    {
        $this->wheres[] = new WhereNull(column: ExpressionFactory::from($column), negated: $negated, boolean: $boolean);

        return $this;
    }

    private function addWhereColumn(
        string|Expression $first,
        string|ComparisonOperator $operator,
        string|Expression $second,
        BooleanOperator $boolean,
    ): self {
        $this->wheres[] = new WhereColumn(
            first: ExpressionFactory::from($first),
            operator: ComparisonOperator::parse($operator),
            second: ExpressionFactory::from($second),
            boolean: $boolean,
        );

        return $this;
    }

    private function addWhereBetween(
        string|Expression $column,
        mixed $from,
        mixed $to,
        bool $negated,
        BooleanOperator $boolean,
    ): self {
        $this->wheres[] = new WhereBetween(
            column: ExpressionFactory::from($column),
            from: $from,
            to: $to,
            negated: $negated,
            boolean: $boolean,
        );

        return $this;
    }

    private function addWhereExists(self $query, bool $negated, BooleanOperator $boolean): self
    {
        $this->wheres[] = new WhereExists(query: $query->toSelectQuery(), negated: $negated, boolean: $boolean);

        return $this;
    }

    private function addNestedWhere(BooleanOperator $boolean, Closure $callback): self
    {
        $nested = $this->newQuery($this->table);

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
            column: ExpressionFactory::from($column),
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
    private function assertMutable(string $operation): void
    {
        if ($this->joins !== []) {
            throw new LogicException(sprintf('Joined %s queries are not supported yet.', $operation));
        }

        if ($this->offset !== null) {
            throw new LogicException(sprintf('%s queries cannot skip rows with an offset.', ucfirst($operation)));
        }
    }
}
