<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use LogicException;
use InvalidArgumentException;
use Dirthara\Database\Query\Clause\Where;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Clause\WhereIn;
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
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Queries\CompiledQuery;

use function count;
use function explode;
use function implode;
use function sprintf;
use function array_map;
use function array_keys;
use function preg_match;
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

    public function compileCount(SelectQuery $query, Expression $column): CompiledQuery
    {
        $bindings = [];
        $counted = $this->counted($query);

        if ($query->groups !== []) {
            $inner = $this->compileSelectSql($counted, $bindings, $this->groupedColumns($query));

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

        $sql = $this->compileSelectSql(
            $counted,
            $bindings,
            sprintf('COUNT(%s) AS %s', $this->wrap($column), $this->quote('aggregate')),
        );

        return new CompiledQuery($sql, $bindings);
    }

    public function compileInsert(InsertQuery $query): CompiledQuery
    {
        $rows = $this->insertRows($query);
        $columns = array_keys($rows[0]);

        $bindings = [];
        $tuples = [];

        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $bindings[] = $this->binding($row[$column]);
            }

            $tuples[] = sprintf('(%s)', implode(', ', array_map(static fn(): string => '?', $columns)));
        }

        return new CompiledQuery(
            sprintf(
                'INSERT INTO %s (%s) VALUES %s',
                $this->wrapTable($query->table),
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

        foreach ($query->values as $column => $value) {
            $assignments[] = sprintf('%s = ?', $this->quote($column));
            $bindings[] = $this->binding($value);
        }

        [$top, $suffix] = $this->compileMutationLimit($query->limit, 'update');

        $sql =
            sprintf('UPDATE%s %s SET %s', $top, $this->wrapTable($query->table), implode(', ', $assignments))
            . $this->compileWhereSection(array_values($query->wheres), $bindings);

        return new CompiledQuery($sql . $suffix, $bindings);
    }

    public function compileDelete(DeleteQuery $query): CompiledQuery
    {
        $bindings = [];

        [$top, $suffix] = $this->compileMutationLimit($query->limit, 'delete');

        $sql =
            sprintf('DELETE%s FROM %s', $top, $this->wrapTable($query->table))
            . $this->compileWhereSection(array_values($query->wheres), $bindings);

        return new CompiledQuery($sql . $suffix, $bindings);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    protected function compileSelectSql(SelectQuery $query, array &$bindings, ?string $columns = null): string
    {
        $sql = sprintf(
            'SELECT %s%s FROM %s',
            $this->compileTop($query),
            $columns ?? implode(', ', array_map($this->wrap(...), $query->columns)),
            $this->wrapTable($query->table),
        );

        foreach ($query->joins as $join) {
            $sql .= ' ' . $this->compileJoin($join);
        }

        $sql .= $this->compileWhereSection($query->wheres, $bindings);

        if ($query->groups !== []) {
            $sql .= ' GROUP BY ' . implode(', ', array_map($this->wrap(...), $query->groups));
        }

        if ($query->havings !== []) {
            $sql .= ' HAVING ' . $this->compileWheres($query->havings, $bindings);
        }

        return $sql . $this->compileOrders($query) . $this->compileLimit($query);
    }

    protected function compileTop(SelectQuery $query): string
    {
        return '';
    }

    protected function compileOrders(SelectQuery $query): string
    {
        if ($query->orders === []) {
            return '';
        }

        return ' ORDER BY ' . implode(', ', array_map($this->compileOrder(...), $query->orders));
    }

    protected function compileJoin(JoinClause $join): string
    {
        return sprintf(
            '%s %s ON %s %s %s',
            $join->type->value,
            $this->wrapTable($join->table),
            $this->wrap($join->first),
            $join->operator->value,
            $this->wrap($join->second),
        );
    }

    protected function compileOrder(OrderBy $order): string
    {
        return sprintf('%s %s', $this->wrap($order->column), $order->direction->value);
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
                $this->wrap($where->column),
                $where->negated ? 'NOT ' : '',
            ),
            $where instanceof WhereIn => $this->compileWhereIn($where, $bindings),
            $where instanceof WhereBetween => $this->compileWhereBetween($where, $bindings),
            $where instanceof WhereColumn => sprintf(
                '%s %s %s',
                $this->wrap($where->first),
                $where->operator->value,
                $this->wrap($where->second),
            ),
            $where instanceof NestedWhere => sprintf('(%s)', $this->compileWheres($where->wheres, $bindings)),
            $where instanceof WhereExists => $this->compileWhereExists($where, $bindings),
            default => throw new LogicException(sprintf('Unsupported where clause [%s].', $where::class)),
        };
    }

    private function unordered(SelectQuery $query): SelectQuery
    {
        if ($query->orders === [] || $query->limit !== null || $query->offset !== null) {
            return $query;
        }

        return $this->counted($query);
    }

    private function counted(SelectQuery $query): SelectQuery
    {
        return new SelectQuery(
            table: $query->table,
            columns: $query->columns,
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
    private function groupedColumns(SelectQuery $query): ?string
    {
        foreach ($query->columns as $column) {
            if ($column->expression !== '*') {
                return null;
            }
        }

        return implode(', ', array_map($this->wrap(...), $query->groups));
    }

    abstract protected function quote(string $identifier): string;

    protected function wrap(Expression $expression): string
    {
        $segments = explode('.', $expression->expression);

        foreach ($segments as $segment) {
            if (!$this->isIdentifier($segment)) {
                return $expression->expression;
            }
        }

        return implode('.', array_map(fn(string $segment): string => $segment === '*'
            ? '*'
            : $this->quote($segment), $segments));
    }

    protected function wrapTable(string $table): string
    {
        return $this->wrap(new Expression($table));
    }

    private function isIdentifier(string $segment): bool
    {
        return $segment === '*' || preg_match('/^[A-Za-z_][A-Za-z0-9_$]*$/', $segment) === 1;
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
        $bindings[] = $this->binding($where->value);

        return sprintf('%s %s ?', $this->wrap($where->column), $where->operator->value);
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function compileWhereIn(WhereIn $where, array &$bindings): string
    {
        if ($where->values === []) {
            return $where->negated ? '1 = 1' : '1 = 0';
        }

        foreach ($where->values as $value) {
            $bindings[] = $this->binding($value);
        }

        return sprintf(
            '%s %sIN (%s)',
            $this->wrap($where->column),
            $where->negated ? 'NOT ' : '',
            implode(', ', array_map(static fn(): string => '?', $where->values)),
        );
    }

    /**
     * @param list<scalar|null> $bindings
     */
    private function compileWhereBetween(WhereBetween $where, array &$bindings): string
    {
        $bindings[] = $this->binding($where->from);
        $bindings[] = $this->binding($where->to);

        return sprintf('%s %sBETWEEN ? AND ?', $this->wrap($where->column), $where->negated ? 'NOT ' : '');
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
