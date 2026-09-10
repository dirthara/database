<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use LogicException;
use Dirthara\Database\Query\Join\JoinType;
use Dirthara\Database\Query\Clause\OrderBy;
use Dirthara\Database\Query\Join\JoinClause;
use Dirthara\Database\Query\Queries\SelectQuery;

use function implode;
use function sprintf;

final class MySqlQueryGrammar extends SqlQueryGrammar
{
    /**
     * The largest row count MySQL accepts for an offset that has no limit of its own.
     */
    private const string UNLIMITED = '18446744073709551615';

    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '`');
    }

    protected function compileJoin(JoinClause $join, array &$bindings): string
    {
        if ($join->type === JoinType::Full) {
            throw new LogicException('MySQL does not support a full join.');
        }

        return parent::compileJoin($join, $bindings);
    }

    protected function compileLimit(SelectQuery $query): string
    {
        if ($query->offset === null) {
            return $query->limit === null ? '' : sprintf(' LIMIT %d', $query->limit);
        }

        return sprintf(
            ' LIMIT %s OFFSET %d',
            $query->limit === null ? self::UNLIMITED : (string) $query->limit,
            $query->offset,
        );
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

        $compiled = [];

        foreach ($orders as $order) {
            $compiled[] = $this->compileOrder($order, $bindings);
        }

        return ' ORDER BY ' . implode(', ', $compiled);
    }

    /**
     * @return array{string, string}
     */
    protected function compileMutationLimit(?int $limit, string $operation): array
    {
        return $limit === null ? ['', ''] : ['', sprintf(' LIMIT %d', $limit)];
    }
}
