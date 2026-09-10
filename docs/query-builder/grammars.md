---
id: grammars
title: Grammars
sidebar_position: 4
description: How each database compiles the same query, and which clauses it refuses.
---

# Grammars

A `QueryGrammar` turns a query object into SQL and its bindings. One ships per
driver, and they share an abstract `SqlQueryGrammar` that holds the clause
compilers; each driver overrides only where its database differs.

| Grammar | Quotes with | Overrides |
| --- | --- | --- |
| `MySqlQueryGrammar` | `` `name` `` | Paging, mutation limit, mutation ordering, rejects `FULL JOIN`. |
| `PostgresSqlQueryGrammar` | `"name"` | Nothing — the standard SQL the base emits is what PostgreSQL wants. |
| `SQLiteQueryGrammar` | `"name"` | Paging. |
| `SqlServerQueryGrammar` | `[name]` | Paging, ordering, mutation limit, existence checks. |

A grammar never guesses. When a clause cannot be expressed on that database it
throws a `LogicException` while compiling, so the query fails where it was built
rather than producing SQL that means something else.

## Quoting

Each name is quoted per dot-separated segment, with the delimiter doubled if it
appears inside a name:

| Input | MySQL | PostgreSQL / SQLite | SQL Server |
| --- | --- | --- | --- |
| `users.name` | `` `users`.`name` `` | `"users"."name"` | `[users].[name]` |
| `users.*` | `` `users`.* `` | `"users".*` | `[users].*` |
| ``we`ird`` | ``` `we``ird` ``` | `"we`ird"` | `[we`ird]` |

## Paging

`LIMIT` is the one clause where all four disagree, including on what to do when
there is an offset but no limit.

| Query | MySQL | PostgreSQL | SQLite | SQL Server |
| --- | --- | --- | --- | --- |
| `limit(10)` | `LIMIT 10` | `LIMIT 10` | `LIMIT 10` | `SELECT TOP (10)` |
| `limit(10)->offset(20)` | `LIMIT 10 OFFSET 20` | `LIMIT 10 OFFSET 20` | `LIMIT 10 OFFSET 20` | `OFFSET 20 ROWS FETCH NEXT 10 ROWS ONLY` |
| `offset(20)` alone | `LIMIT 18446744073709551615 OFFSET 20` | `OFFSET 20` | `LIMIT -1 OFFSET 20` | `OFFSET 20 ROWS` |

PostgreSQL takes a bare `OFFSET`. MySQL does not, and its documented workaround
is the largest row count it accepts. SQLite reads `-1` as no limit. SQL Server
has no `LIMIT` at all: a limit alone becomes `TOP (n)` in the select list, and
anything with an offset becomes `OFFSET … FETCH`.

:::note
SQL Server's `OFFSET … FETCH` is only valid after an `ORDER BY`. A paged query
with no ordering of its own gets `ORDER BY (SELECT NULL)` so it compiles — the
rows come back in whatever order the server chooses, exactly as an unordered
query already implies.
:::

## Joins

| Join | MySQL | PostgreSQL | SQLite | SQL Server |
| --- | --- | --- | --- | --- |
| `Inner`, `Left` | yes | yes | yes | yes |
| `Right` | yes | yes | 3.39+ | yes |
| `Full` | **throws** | yes | 3.39+ | yes |

MySQL has no `FULL JOIN`, so `MySqlQueryGrammar` rejects it:

```
MySQL does not support a full join.
```

:::caution
SQLite gained `RIGHT JOIN` and `FULL JOIN` in 3.39 (June 2022). The grammar
emits them, and an older SQLite reports a syntax error. `pdo_sqlite` links
whatever `libsqlite3` the machine has, so this depends on the install rather
than on the package.
:::

## Existence checks

`exists()` wraps the query. PostgreSQL, MySQL, and SQLite allow `EXISTS` in a
select list; SQL Server does not, so it compiles a `CASE` instead:

```sql
-- MySQL, PostgreSQL, SQLite
SELECT EXISTS(SELECT * FROM "users" WHERE "active" = ?) AS "exists"

-- SQL Server
SELECT CASE WHEN EXISTS(SELECT * FROM [users] WHERE [active] = ?) THEN 1 ELSE 0 END AS [exists]
```

Ordering is dropped from the wrapped query, since ordering rows that only need
to be counted changes nothing — and SQL Server rejects `ORDER BY` in a subquery
that has no `TOP` or `OFFSET`.

## Counting

`count()` drops ordering and paging. Over a grouped query it counts the groups by
wrapping the query in a derived table:

```sql
SELECT COUNT(*) AS `aggregate` FROM (SELECT `role` FROM `users` GROUP BY `role`) AS `aggregate`
```

When nothing was selected, the derived table selects the grouped columns rather
than `*`, because `SELECT *` beside a `GROUP BY` is rejected by MySQL under
`only_full_group_by`, by PostgreSQL always, and by SQL Server always.

## Mutations

| Clause | MySQL | PostgreSQL | SQLite | SQL Server |
| --- | --- | --- | --- | --- |
| `limit()` on update/delete | `LIMIT n` | **throws** | **throws** | `UPDATE TOP (n)` / `DELETE TOP (n)` |
| `orderBy()` on update/delete | `ORDER BY …` | **throws** | **throws** | **throws** |

PostgreSQL has neither on a mutation. SQL Server can limit but not order, so
`TOP (n)` picks an arbitrary row — which is why an ordering is refused rather
than dropped.

:::caution
SQLite supports both, but only when `libsqlite3` was compiled with
`SQLITE_ENABLE_UPDATE_DELETE_LIMIT`, which is off by default in the amalgamation
and on in some distributions. The grammar refuses rather than emitting SQL whose
validity depends on the machine — a query that works in CI and fails on a user's
server is worse than one that fails everywhere.
:::

## Writing a grammar

Extend `SqlQueryGrammar` and implement `quote()`. That is the whole requirement;
everything else has a working default.

```php
use Dirthara\Database\Query\Grammar\SqlQueryGrammar;

final class MariaDbQueryGrammar extends SqlQueryGrammar
{
    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '`');
    }
}
```

`escape()` takes an optional closing delimiter for databases whose quotes are
not symmetric, which is how the SQL Server grammar produces `[name]`.

The seams a driver can override:

| Method | Controls |
| --- | --- |
| `quote()` | How one name segment is quoted. Required. |
| `compileTop()` | Text between `SELECT` and the column list. |
| `compileLimit()` | The paging clause at the end of a select. |
| `compileOrders()` | The `ORDER BY` clause of a select. |
| `compileJoin()` | One join, including rejecting a join type. |
| `compileMutationLimit()` | Where a limit goes on an update or delete, or whether it is refused. |
| `compileMutationOrders()` | The ordering of an update or delete, or whether it is refused. |
| `wrapExists()` | How an existence check is wrapped. |

Register it on the [resolver](../database.md#resolving-grammars) against the
driver it belongs to.

## A caveat that is SQL's, not the builder's

A bound parameter inside a `GROUP BY` expression cannot be matched to the same
expression in the select list, because the server sees two separate placeholders
and cannot prove they are equal:

```php
$database->table('users')
    ->selectRaw('COALESCE(role, ?) AS role', ['none'])
    ->groupByRaw('COALESCE(role, ?)', ['none'])
    ->get();
```

MySQL, PostgreSQL, and SQL Server all reject that; SQLite allows it. Put the
literal in the fragment, or select only aggregates, and it compiles everywhere.
