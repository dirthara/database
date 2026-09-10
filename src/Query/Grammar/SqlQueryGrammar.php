<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use LogicException;
use InvalidArgumentException;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Clause\WhereIn;
use Dirthara\Database\Query\Clause\RawWhere;
use Dirthara\Database\Query\Join\JoinClause;
use Dirthara\Database\Query\Clause\WhereNull;
use Dirthara\Database\Query\Clause\NestedWhere;
use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Query\Clause\WhereColumn;
use Dirthara\Database\Query\Clause\WhereExists;
use Dirthara\Database\Query\Expression\Aliased;
use Dirthara\Database\Query\Clause\WhereBetween;
use Dirthara\Database\Query\Queries\DeleteQuery;
use Dirthara\Database\Query\Queries\InsertQuery;
use Dirthara\Database\Query\Queries\SelectQuery;
use Dirthara\Database\Query\Queries\UpdateQuery;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Expression\RawExpression;
use Dirthara\Database\Query\Aggregate\AggregateFunction;

use function count;
use function explode;
use function implode;
use function sprintf;
use function array_map;
use function array_keys;
use function array_merge;
use function str_replace;
use function array_values;
use function array_is_list;

abstract class SqlQueryGrammar implements QueryGrammar
{
    public function compileSelect(SelectQuery $query): CompiledQuery
    {
        $bindings = [];
        $sql = $this->compileSelectSql($query, $bindings);

        return new CompiledQuery($sql, $bindings);
    }

    public function compileExists(SelectQuery $query): CompiledQuery
    {
        $bindings = [];
        $sql = $this->compileSelectSql($this->unordered($query), $bindings);

        return new CompiledQuery($this->wrapExists($sql), $bindings);
    }

    protected function wrapExists(string $select): string
    {
        return sprintf('SELECT EXISTS(%s) AS %s', $select, $this->quote('exists'));
    }

    public function compileAggregate(SelectQuery $query, AggregateFunction $function, Expression $column): CompiledQuery
    {
        $bindings = [];

        if ($function === AggregateFunction::Count && ($query->groups !== [] || $query->distinct)) {
            $inner = $this->compileSelectSql(
                $this->aggregated($query, $query->distinct),
                $bindings,
                $this->groupedColumns($query, $bindings),
            );

            return new CompiledQuery(
                sprintf(
                    'SELECT COUNT(*) AS %s FROM (%s) AS %s',
                    $this->quote('aggregate'),
                    $inner,
                    $this->quote('aggregate'),
                ),
                $bindings,
            );
        }

        $columns = sprintf(
            '%s(%s%s) AS %s',
            $function->value,
            $query->distinct ? 'DISTINCT ' : '',
            $this->wrap($column, $bindings),
            $this->quote('aggregate'),
        );

        $sql = $this->compileSelectSql($this->aggregated($query, false), $bindings, $columns);

        return new CompiledQuery($sql, $bindings);
    }

    public function compileInsert(InsertQuery $query): CompiledQuery
    {
        $rows = $this->insertRows($query);
        $columns = array_keys($rows[0]);

        $bindings = [];
        $tuples = [];
        $table = $this->wrap($query->table, $bindings);

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $this->binding($row[$column]);
            }

            $tuples[] = sprintf('(%s)', implode(', ', array_map(static fn(): string => '?', $columns)));
        }

        return new CompiledQuery(
            sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $table,
                implode(', ', array_map($this->quote(...), $columns)),
                implode(', ', $tuples),
            ),
            $bindings,
        );
    }

    public function compileUpdate(UpdateQuery $query): CompiledQuery
    {
        if ($query->values === []) {
            throw new InvalidArgumentException('An update needs at least one value.');
        }

        $bindings = [];
        $assignments = [];
        $table = $this->wrap($query->table, $bindings);

        foreach ($query->values as $column => $value) {
            $assignments[] = sprintf('%s = ?', $this->quote($column));
            $bindings[] = $this->binding($value);
        }

        [$top, $suffix] = $this->compileMutationLimit($query->limit, 'update');

        $wheres = $this->compileWhereSection(array_values($query->wheres), $bindings);
        $orders = $this->compileMutationOrders($query->orders, 'update', $bindings);

        return new CompiledQuery(
            sprintf('UPDATE%s %s SET %s%s%s%s', $top, $table, implode(', ', $assignments), $wheres, $orders, $suffix),
            $bindings,
        );
    }

    public function compileDelete(DeleteQuery $query): CompiledQuery
    {
        $bindings = [];

        [$top, $suffix] = $this->compileMutationLimit($query->limit, 'delete');

        $table = $this->wrap($query->table, $bindings);

        $wheres = $this->compileWhereSection(array_values($query->wheres), $bindings);
        $orders = $this->compileMutationOrders($query->orders, 'delete', $bindings);

        return new CompiledQuery(sprintf('DELETE%s FROM %s%s%s%s', $top, $table, $wheres, $orders, $suffix), $bindings);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    protected function compileSelectSql(SelectQuery $query, array &$bindings, ?string $columns = null): string
    {
        $columns ??= $this->compileExpressions($query->columns, $bindings);

        $sql = sprintf(
            'SELECT %s%s%s FROM %s',
            $query->distinct ? 'DISTINCT ' : '',
            $this->compileTop($query),
            $columns,
            $this->wrap($query->table, $bindings),
        );

        foreach ($query->joins as $join) {
            $sql .= ' ' . $this->compileJoin($join, $bindings);
        }

        $sql .= $this->compileWhereSection($query->wheres, $bindings);

        if ($query->groups !== []) {
            $sql .= ' GROUP BY ' . $this->compileExpressions($query->groups, $bindings);
        }

        if ($query->havings !== []) {
            $sql .= ' HAVING ' . $this->compileWheres($query->havings, $bindings);
        }

        return $sql . $this->compileOrders($query, $bindings) . $this->compileLimit($query);
    }

    /**
     * @param list<Expression> $expressions
     * @param list<scalar|null> $bindings
     */
    protected function compileExpressions(array $expressions, array &$bindings): string
    {
        $compiled = [];

        foreach ($expressions as $expression) {
            $compiled[] = $this->wrap($expression, $bindings);
        }

        return implode(', ', $compiled);
    }

    protected function compileTop(SelectQuery $query): string
    {
        return '';
    }

    /**
     * @param list<scalar|null> $bindings
     */
    protected function compileOrders(SelectQuery $query, array &$bindings): string
    {
        if ($query->orders === []) {
            return '';
        }

        $compiled = [];

        foreach ($query->orders as $order) {
            $compiled[] = $this->compileOrder($order, $bindings);
        }

        return ' ORDER BY ' . implode(', ', $compiled);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    protected function compileJoin(JoinClause $join, array &$bindings): string
    {
        return sprintf(
            '%s %s ON %s %s %s',
            $join->type->value,
            $this->wrap($join->table, $bindings),
            $this->wrap($join->first, $bindings),
            $join->operator->value,
            $this->wrap($join->second, $bindings),
        );
    }

    /**
     * @param list<scalar|null> $bindings
     */
    protected function compileOrder(OrderBy $order, array &$bindings): string
    {
        return sprintf('%s %s', $this->wrap($order->column, $bindings), $order->direction->value);
    }

    protected function compileLimit(SelectQuery $query): string
    {
        $sql = '';

        if ($query->limit !== null) {
            $sql .= sprintf(' LIMIT %d', $query->limit);
        }

        if ($query->offset !== null) {
            $sql .= sprintf(' OFFSET %d', $query->offset);
        }

        return $sql;
    }

    /**
     * @param list<OrderBy> $orders
     * @param list<scalar|null> $bindings
     */
    protected function compileMutationOrders(array $orders, string $operation, array &$bindings): string
    {
        if ($orders === []) {
            return '';
        }

        throw new LogicException(sprintf('An ordered %s query is not supported by this driver.', $operation));
    }

    /**
     * @return array{string, string} the text after the keyword and the text after the where clause
     */
    protected function compileMutationLimit(?int $limit, string $operation): array
    {
        if ($limit === null) {
            return ['', ''];
        }

        throw new LogicException(sprintf('A limited %s query is not supported by this driver.', $operation));
    }

    /**
     * @param list<WhereClause> $wheres
     * @param list<scalar|null> $bindings
     */
    protected function compileWhereSection(array $wheres, array &$bindings): string
    {
        if ($wheres === []) {
            return '';
        }

        return ' WHERE ' . $this->compileWheres($wheres, $bindings);
    }

    /**
     * @param list<WhereClause> $wheres
     * @param list<scalar|null> $bindings
     */
    protected function compileWheres(array $wheres, array &$bindings): string
    {
        $sql = '';

        foreach ($wheres as $index => $where) {
            if ($index > 0) {
                $sql .= sprintf(
                    ' %s ',
                    $where instanceof NestedWhere ? $where->boolean->value : $this->boolean($where),
                );
            }

            $sql .= $this->compileWhere($where, $bindings);
        }

        return $sql;
    }

    /**
     * @param list<scalar|null> $bindings
     */
    protected function compileWhere(WhereClause $where, array &$bindings): string
    {
        return match (true) {
            $where instanceof Where => $this->compileBasicWhere($where, $bindings),
            $where instanceof WhereNull => sprintf(
                '%s IS %sNULL',
                $this->wrap($where->column, $bindings),
                $where->negated ? 'NOT ' : '',
            ),
            $where instanceof WhereIn => $this->compileWhereIn($where, $bindings),
            $where instanceof WhereBetween => $this->compileWhereBetween($where, $bindings),
            $where instanceof WhereColumn => sprintf(
                '%s %s %s',
                $this->wrap($where->first, $bindings),
                $where->operator->value,
                $this->wrap($where->second, $bindings),
            ),
            $where instanceof NestedWhere => sprintf('(%s)', $this->compileWheres($where->wheres, $bindings)),
            $where instanceof RawWhere => $this->compileRawWhere($where, $bindings),
            $where instanceof WhereExists => $this->compileWhereExists($where, $bindings),
            default => throw new LogicException(sprintf('Unsupported where clause [%s].', $where::class)),
        };
    }

    private function unordered(SelectQuery $query): SelectQuery
    {
        if ($query->orders === [] || $query->limit !== null || $query->offset !== null) {
            return $query;
        }

        return $this->aggregated($query, $query->distinct);
    }

    private function aggregated(SelectQuery $query, bool $distinct): SelectQuery
    {
        return new SelectQuery(
            table: $query->table,
            columns: $query->columns,
            distinct: $distinct,
            joins: $query->joins,
            wheres: $query->wheres,
            groups: $query->groups,
            havings: $query->havings,
            orders: [],
            limit: null,
            offset: null,
        );
    }

    /**
     * The columns a grouped count selects: the groups themselves, unless columns were chosen.
     */
    /**
     * @param list<scalar|null> $bindings
     */
    private function groupedColumns(SelectQuery $query, array &$bindings): ?string
    {
        foreach ($query->columns as $column) {
            if (!$column instanceof Identifier || $column->name !== '*') {
                return null;
            }
        }

        return $this->compileExpressions($query->groups, $bindings);
    }

    abstract protected function quote(string $identifier): string;

    /**
     * @param list<scalar|null> $bindings
     */
    protected function wrap(Expression $expression, array &$bindings): string
    {
        if ($expression instanceof Identifier) {
            return $this->quoteSegments($expression->name);
        }

        if ($expression instanceof RawExpression) {
            foreach ($expression->bindings as $binding) {
                $bindings[] = $binding;
            }

            return $expression->sql;
        }

        if ($expression instanceof Aliased) {
            return sprintf(
                '%s AS %s',
                $this->wrap($expression->expression, $bindings),
                $this->quote($expression->alias),
            );
        }

        throw new LogicException(sprintf('Unsupported expression [%s].', $expression::class));
    }

    private function quoteSegments(string $name): string
    {
        $segments = [];

        foreach (explode('.', $name) as $segment) {
            $segments[] = $segment === '*' ? '*' : $this->quote($segment);
        }

        return implode('.', $segments);
    }

    protected function escape(string $identifier, string $open, ?string $close = null): string
    {
        $close ??= $open;

        return $open . str_replace($close, $close . $close, $identifier) . $close;
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function compileBasicWhere(Where $where, array &$bindings): string
    {
        $column = $this->wrap($where->column, $bindings);
        $bindings[] = $this->binding($where->value);

        return sprintf('%s %s ?', $column, $where->operator->value);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function compileWhereIn(WhereIn $where, array &$bindings): string
    {
        if ($where->values === []) {
            return $where->negated ? '1 = 1' : '1 = 0';
        }

        $column = $this->wrap($where->column, $bindings);

        foreach ($where->values as $value) {
            $bindings[] = $this->binding($value);
        }

        return sprintf(
            '%s %sIN (%s)',
            $column,
            $where->negated ? 'NOT ' : '',
            implode(', ', array_map(static fn(): string => '?', $where->values)),
        );
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function compileWhereBetween(WhereBetween $where, array &$bindings): string
    {
        $column = $this->wrap($where->column, $bindings);

        $bindings[] = $this->binding($where->from);
        $bindings[] = $this->binding($where->to);

        return sprintf('%s %sBETWEEN ? AND ?', $column, $where->negated ? 'NOT ' : '');
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function compileRawWhere(RawWhere $where, array &$bindings): string
    {
        foreach ($where->bindings as $binding) {
            $bindings[] = $binding;
        }

        return sprintf('(%s)', $where->sql);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function compileWhereExists(WhereExists $where, array &$bindings): string
    {
        $subquery = $this->compileSelect($this->unordered($where->query));

        $bindings = array_merge($bindings, $subquery->bindings);

        return sprintf('%sEXISTS (%s)', $where->negated ? 'NOT ' : '', $subquery->sql);
    }

    private function boolean(WhereClause $where): string
    {
        return match (true) {
            $where instanceof Where => $where->boolean->value,
            $where instanceof WhereNull => $where->boolean->value,
            $where instanceof WhereIn => $where->boolean->value,
            $where instanceof WhereBetween => $where->boolean->value,
            $where instanceof WhereColumn => $where->boolean->value,
            $where instanceof WhereExists => $where->boolean->value,
            $where instanceof NestedWhere => $where->boolean->value,
            $where instanceof RawWhere => $where->boolean->value,
            default => throw new LogicException(sprintf('Unsupported where clause [%s].', $where::class)),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function insertRows(InsertQuery $query): array
    {
        $rows = $query->rows;

        if ($rows === []) {
            throw new InvalidArgumentException('An insert needs at least one row.');
        }

        if (!array_is_list($rows)) {
            /** @var array<string, mixed> $rows */
            return [$rows];
        }

        /** @var list<array<string, mixed>> $rows */
        $columns = array_keys($rows[0]);

        if ($columns === []) {
            throw new InvalidArgumentException('An insert needs at least one column.');
        }

        foreach ($rows as $row) {
            if (array_keys($row) !== $columns) {
                throw new InvalidArgumentException('Every inserted row needs the same columns in the same order.');
            }
        }

        return $rows;
    }

    private function binding(mixed $value): string|int|float|bool|null
    {
        if ($value === null || is_scalar($value)) {
            return $value;
        }

        throw new InvalidArgumentException(sprintf(
            'A query binding must be scalar or null, got [%s].',
            get_debug_type($value),
        ));
    }
}
