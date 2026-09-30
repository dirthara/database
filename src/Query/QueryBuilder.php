<?php

declare(strict_types=1);

namespace Dirthara\Database\Query;

use Closure;
use Dirthara\Database\Query\Clause\Lock;
use Dirthara\Database\Query\Clause\Union;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Sql\JoinType;
use Dirthara\Database\Query\Sql\LockMode;
use Dirthara\Database\Query\Sql\LockWait;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Clause\WhereIn;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Query\Clause\RawWhere;
use Dirthara\Database\Query\Clause\WhereNull;
use Dirthara\Database\Query\Clause\JoinClause;
use Dirthara\Database\Connection\Result\Result;
use Dirthara\Database\Exception\QueryException;
use Dirthara\Database\Query\Clause\NestedWhere;
use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Clause\WhereColumn;
use Dirthara\Database\Query\Clause\WhereExists;
use Dirthara\Database\Query\Sql\OrderDirection;
use Dirthara\Database\Query\Clause\WhereBetween;
use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Sql\BooleanOperator;
use Dirthara\Database\Exception\RowLockException;
use Dirthara\Database\Query\Grammar\QueryGrammar;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Sql\AggregateFunction;
use Dirthara\Database\Query\Sql\ComparisonOperator;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Query\Expression\RawExpression;
use Dirthara\Database\Exception\InvalidQueryException;
use Dirthara\Database\Exception\UnsupportedLockException;
use Dirthara\Database\Query\Expression\ExpressionFactory;
use Dirthara\Database\Exception\UnsupportedQueryException;

use function trim;
use function count;
use function reset;
use function sprintf;
use function is_array;
use function array_map;
use function is_scalar;
use function is_string;
use function array_push;
use function array_values;
use function array_is_list;
use function iterator_to_array;

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

    /**
     * @var list<Union>
     */
    private array $unions = [];

    private bool $distinct = false;

    private ?int $limit = null;

    private ?int $offset = null;

    private ?Lock $lock = null;

    private readonly Expression $table;

    public function __construct(
        private readonly Connection $connection,
        private readonly QueryGrammar $grammar,
        string|Expression $table,
    ) {
        if (is_string($table) && trim($table) === '') {
            throw InvalidQueryException::emptyTable();
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

    public function union(self $query, bool $all = false): self
    {
        $operand = $query->toSelectQuery();

        if ($operand->orders !== [] || $operand->limit !== null || $operand->offset !== null) {
            throw InvalidQueryException::orderedUnionOperand();
        }

        $this->unions[] = new Union(query: $operand, all: $all);

        return $this;
    }

    public function unionAll(self $query): self
    {
        return $this->union($query, true);
    }

    public function distinct(bool $distinct = true): self
    {
        $this->distinct = $distinct;

        return $this;
    }

    public function where(string|Expression $column, string|ComparisonOperator $operator, mixed $value): self
    {
        return $this->addBasicWhere(column: $column, operator: $operator, value: $value, boolean: BooleanOperator::And);
    }

    public function orWhere(string|Expression $column, string|ComparisonOperator $operator, mixed $value): self
    {
        return $this->addBasicWhere(column: $column, operator: $operator, value: $value, boolean: BooleanOperator::Or);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        return $this->addWhereRaw($sql, $bindings, BooleanOperator::And);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->addWhereRaw($sql, $bindings, BooleanOperator::Or);
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

    public function having(string|Expression $column, string|ComparisonOperator $operator, mixed $value): self
    {
        return $this->addHaving(
            column: ExpressionFactory::from($column),
            operator: ComparisonOperator::parse($operator),
            value: $value,
            boolean: BooleanOperator::And,
        );
    }

    public function orHaving(string|Expression $column, string|ComparisonOperator $operator, mixed $value): self
    {
        return $this->addHaving(
            column: ExpressionFactory::from($column),
            operator: ComparisonOperator::parse($operator),
            value: $value,
            boolean: BooleanOperator::Or,
        );
    }

    /**
     * @param list<scalar|null> $bindings
     */
    public function havingRaw(string $sql, array $bindings = []): self
    {
        return $this->addHavingRaw($sql, $bindings, BooleanOperator::And);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    public function orHavingRaw(string $sql, array $bindings = []): self
    {
        return $this->addHavingRaw($sql, $bindings, BooleanOperator::Or);
    }

    public function havingNull(string|Expression $column): self
    {
        return $this->addHavingNull($column, false, BooleanOperator::And);
    }

    public function orHavingNull(string|Expression $column): self
    {
        return $this->addHavingNull($column, false, BooleanOperator::Or);
    }

    public function havingNotNull(string|Expression $column): self
    {
        return $this->addHavingNull($column, true, BooleanOperator::And);
    }

    public function orHavingNotNull(string|Expression $column): self
    {
        return $this->addHavingNull($column, true, BooleanOperator::Or);
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
            throw InvalidQueryException::negativeLimit($limit);
        }

        $this->limit = $limit;

        return $this;
    }

    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw InvalidQueryException::negativeOffset($offset);
        }

        $this->offset = $offset;

        return $this;
    }

    public function forUpdate(LockWait $wait = LockWait::Wait): self
    {
        return $this->lock(LockMode::Update, $wait);
    }

    public function forShare(LockWait $wait = LockWait::Wait): self
    {
        return $this->lock(LockMode::Share, $wait);
    }

    public function lock(LockMode $mode, LockWait $wait = LockWait::Wait): self
    {
        $this->lock = new Lock(mode: $mode, wait: $wait);

        return $this;
    }

    public function withoutLock(): self
    {
        $this->lock = null;

        return $this;
    }

    /**
     * @return list<array<string, mixed>>
     *
     * @throws QueryException
     * @throws ConnectionException
     * @throws RowLockException
     * @throws UnsupportedLockException
     */
    public function get(): array
    {
        return $this->runSelect($this->toSelectQuery())->all();
    }

    /**
     * @return iterable<array<string, mixed>>
     *
     * @throws QueryException
     * @throws ConnectionException
     * @throws RowLockException
     * @throws UnsupportedLockException
     */
    public function cursor(): iterable
    {
        yield from $this->runSelect($this->toSelectQuery())->iterate();
    }

    /**
     * @param callable(list<array<string, mixed>>, int): mixed $callback
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function chunk(int $size, callable $callback): bool
    {
        $this->assertUnlocked('chunk()');

        if ($size < 1) {
            throw InvalidQueryException::invalidChunkSize($size);
        }

        if ($this->orders === []) {
            throw InvalidQueryException::unorderedChunk();
        }

        if ($this->limit !== null || $this->offset !== null) {
            throw InvalidQueryException::pagedChunk();
        }

        $page = 1;

        while (true) {
            $rows = (clone $this)
                ->limit($size)
                ->offset(($page - 1) * $size)
                ->get();

            if ($rows === []) {
                return true;
            }

            if ($callback($rows, $page) === false) {
                return false;
            }

            if (count($rows) < $size) {
                return true;
            }

            ++$page;
        }
    }

    /**
     * @return array<string, mixed>|null
     *
     * @throws QueryException
     * @throws ConnectionException
     * @throws RowLockException
     * @throws UnsupportedLockException
     */
    public function first(): ?array
    {
        $query = clone $this;

        $query->limit(1);

        return $this->runSelect($query->toSelectQuery())->first();
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

        // @mago-expect analysis:mixed-assignment
        $value = reset($row);

        return is_scalar($value) && (bool) $value;
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function count(string|Expression $column = '*'): int
    {
        return (int) $this->aggregate(AggregateFunction::Count, $column);
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function sum(string|Expression $column): string|int|float|bool|null
    {
        return $this->aggregate(AggregateFunction::Sum, $column);
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function avg(string|Expression $column): string|int|float|bool|null
    {
        return $this->aggregate(AggregateFunction::Average, $column);
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function min(string|Expression $column): string|int|float|bool|null
    {
        return $this->aggregate(AggregateFunction::Minimum, $column);
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    public function max(string|Expression $column): string|int|float|bool|null
    {
        return $this->aggregate(AggregateFunction::Maximum, $column);
    }

    /**
     * @param array<string, mixed>|list<array<string, mixed>> $values
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function insert(array $values): int
    {
        $this->assertUnlocked('insert()');

        if ($values === []) {
            return 0;
        }

        $rows = $this->normaliseInsertRows($values);

        $query = $this->grammar->compileInsert(new InsertQuery(table: $this->table, rows: $rows));

        return $this->connection->execute($query->sql, $query->bindings)->affectedRows();
    }

    /**
     * @param array<string, scalar|null> $values
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function insertGetId(array $values, string $key = 'id'): ?string
    {
        $this->assertUnlocked('insertGetId()');

        $rows = $this->normaliseInsertRows($values);

        if (count($rows) !== 1) {
            throw InvalidQueryException::insertGetIdRowCount(count($rows));
        }

        $insert = new InsertQuery(table: $this->table, rows: $rows);
        $returning = $this->grammar->compileInsertReturning($insert, new Identifier($key));

        if ($returning === null) {
            $compiled = $this->grammar->compileInsert($insert);

            $this->connection->execute($compiled->sql, $compiled->bindings);

            return $this->connection->lastInsertId();
        }

        $row = $this->connection->execute($returning->sql, $returning->bindings)->first();

        if ($row === null) {
            return null;
        }

        // @mago-expect analysis:mixed-assignment
        $value = reset($row);

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param array<string, scalar|null> $values
     *
     * @throws QueryException
     * @throws ConnectionException
     */
    public function update(array $values): int
    {
        $this->assertUnlocked('update()');

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
        $this->assertUnlocked('delete()');

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

    /**
     * @internal the query objects are not part of the public API
     */
    public function toSelectQuery(): SelectQuery
    {
        return new SelectQuery(
            table: $this->table,
            columns: $this->columns === [] ? [new Identifier('*')] : $this->columns,
            distinct: $this->distinct,
            joins: $this->joins,
            wheres: $this->wheres,
            groups: $this->groups,
            havings: $this->havings,
            unions: $this->unions,
            orders: $this->orders,
            limit: $this->limit,
            offset: $this->offset,
            lock: $this->lock,
        );
    }

    /**
     * @throws QueryException
     * @throws ConnectionException
     * @throws RowLockException
     * @throws UnsupportedLockException
     */
    private function runSelect(SelectQuery $query): Result
    {
        $compiled = $this->grammar->compileSelect($query);

        if ($query->lock !== null && !$this->connection->transactions()->inTransaction()) {
            throw RowLockException::outsideTransaction($this->connection->name(), $query->lock);
        }

        return $this->connection->execute($compiled->sql, $compiled->bindings);
    }

    /**
     * @throws UnsupportedLockException
     */
    private function assertUnlocked(string $operation): void
    {
        if ($this->lock !== null) {
            throw UnsupportedLockException::lockedOperation($operation, $this->lock);
        }
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

            throw InvalidQueryException::nullComparison(sprintf('%s (%s)', $operator->name, $operator->value));
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

    /**
     * @throws QueryException
     * @throws ConnectionException
     */
    private function aggregate(AggregateFunction $function, string|Expression $column): string|int|float|bool|null
    {
        if ($function !== AggregateFunction::Count && $this->groups !== []) {
            throw InvalidQueryException::groupedAggregate($function->value);
        }

        $query = $this->grammar->compileAggregate($this->toSelectQuery(), $function, ExpressionFactory::from($column));

        $row = $this->connection->execute($query->sql, $query->bindings)->first();

        if ($row === null) {
            return null;
        }

        // @mago-expect analysis:mixed-assignment
        $value = reset($row);

        return is_scalar($value) ? $value : null;
    }

    private function addWhereNull(string|Expression $column, bool $negated, BooleanOperator $boolean): self
    {
        $this->wheres[] = $this->nullClause($column, $negated, $boolean);

        return $this;
    }

    private function addHavingNull(string|Expression $column, bool $negated, BooleanOperator $boolean): self
    {
        $this->havings[] = $this->nullClause($column, $negated, $boolean);

        return $this;
    }

    private function nullClause(string|Expression $column, bool $negated, BooleanOperator $boolean): WhereNull
    {
        return new WhereNull(column: ExpressionFactory::from($column), negated: $negated, boolean: $boolean);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function addWhereRaw(string $sql, array $bindings, BooleanOperator $boolean): self
    {
        $this->wheres[] = new RawWhere(sql: $sql, bindings: $bindings, boolean: $boolean);

        return $this;
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function addHavingRaw(string $sql, array $bindings, BooleanOperator $boolean): self
    {
        $this->havings[] = new RawWhere(sql: $sql, bindings: $bindings, boolean: $boolean);

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
        if ($value === null) {
            throw InvalidQueryException::nullHaving();
        }

        $this->havings[] = new Where(
            column: ExpressionFactory::from($column),
            operator: $operator,
            value: $value,
            boolean: $boolean,
        );

        return $this;
    }

    /**
     * @param array<array-key, mixed> $values
     *
     * @return list<array<string, mixed>>
     *
     * @throws InvalidQueryException
     */
    private function normaliseInsertRows(array $values): array
    {
        if (array_is_list($values)) {
            // @mago-expect analysis:mixed-assignment
            foreach ($values as $row) {
                if (!is_array($row)) {
                    throw InvalidQueryException::invalidBulkInsert();
                }
            }

            /** @var list<array<string, mixed>> $values */
            return $values;
        }

        /** @var array<string, mixed> $values */
        return [$values];
    }

    /**
     * @throws InvalidQueryException
     * @throws UnsupportedQueryException
     */
    private function assertMutable(string $operation): void
    {
        if ($this->joins !== []) {
            throw UnsupportedQueryException::joinedMutation($operation);
        }

        if ($this->offset !== null) {
            throw InvalidQueryException::offsetMutation($operation);
        }
    }
}
