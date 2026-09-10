---
id: building-queries
title: Building queries
sidebar_position: 1
description: Select columns, add conditions, join tables, group, order, and page a query.
---

# Building queries

A builder comes from [`Database::table()`](../database.md) and collects clauses
until something runs it.

```php
$rows = $database->table('users')
    ->select('id', 'name')
    ->where('active', '=', 1)
    ->orderBy('name')
    ->limit(20)
    ->get();
```

Every clause method returns the same builder, so the order you call them in does
not matter. The grammar assembles them in the order SQL needs.

:::note
A builder is mutable. Calling `->where(...)` changes the builder rather than
returning a copy, so passing one around shares it. `newQuery()` gives you a
fresh one.
:::

## Selecting columns

| Method | Effect |
| --- | --- |
| `select(...$columns)` | Replaces the selection. With no arguments it clears it. |
| `addSelect(...$columns)` | Appends to the selection. |
| `selectRaw(string $sql, array $bindings = [])` | Appends a raw SQL fragment. |
| `distinct(bool $distinct = true)` | Emits `SELECT DISTINCT`. Pass `false` to turn it off again. |

With nothing selected, the query selects `*`.

```php
$database->table('users')->select('id', 'users.name')->addSelect('email');

$database->table('users')->selectRaw('COUNT(*) AS total');
```

Column names are quoted; raw fragments are not. Which is which is the subject of
[Expressions](expressions.md), and it is the one thing worth reading before you
write much of this.

## Conditions

Every condition has an `and` form and an `or` form. The first condition in a
group ignores its own boolean, so `where()` and `orWhere()` are interchangeable
as the opener.

| Method | Compiles to |
| --- | --- |
| `where($column, $operator, $value)` | `column op ?` |
| `whereNull($column)` / `whereNotNull($column)` | `column IS NULL` / `IS NOT NULL` |
| `whereIn($column, $values)` / `whereNotIn(...)` | `column IN (?, ?)` / `NOT IN (…)` |
| `whereBetween($column, $from, $to)` / `whereNotBetween(...)` | `column BETWEEN ? AND ?` |
| `whereColumn($first, $operator, $second)` | `first op second` — no binding |
| `whereNested(Closure $callback)` | `(…)` around whatever the callback adds |
| `whereExists(QueryBuilder $query)` / `whereNotExists(...)` | `EXISTS (…)` / `NOT EXISTS (…)` |
| `whereRaw(string $sql, array $bindings = [])` | The fragment as written, in parentheses |

```php
$database->table('users')
    ->where('role', '=', 'admin')
    ->orWhere('role', '=', 'owner')
    ->whereNotNull('confirmed_at')
    ->whereIn('team_id', [1, 2, 3])
    ->whereBetween('age', 18, 65)
    ->whereColumn('created_at', '<', 'updated_at');
```

### Operators

The operator is a string or a `ComparisonOperator`. Strings are read
case-insensitively, extra whitespace is collapsed, and `<>` is read as `!=`.

| Operator | Also accepted as |
| --- | --- |
| `=` | |
| `!=` | `<>` |
| `>`, `>=`, `<`, `<=` | |
| `LIKE`, `NOT LIKE` | `like`, `not like`, `Not   Like` |

That is the whole list, because it is the whole set of operators that compare two
values. `IN`, `IS NULL` and `BETWEEN` are not operators here — they are the
clauses in the table above, and passing one as an operator throws:

```php
$database->table('users')->where('id', 'IN', [1, 2]);
// InvalidArgumentException: The operator [IN] cannot compare two values;
// expected one of =, !=, >, >=, <, <=, LIKE, NOT LIKE.
```

The list is also closed, which means an operator your database has and this one
does not — PostgreSQL's `ILIKE` or `@>`, MySQL's `<=>`, `IS DISTINCT FROM` — is
not reachable through `where()`. Use [`whereRaw()`](#raw-conditions) for those.

:::caution
Do not reach for a raw *expression* instead. `where(new RawExpression('name ILIKE ?', […]), '=', true)`
compiles to `WHERE name ILIKE ? = ?`, which is not what you meant and is not
rejected. A whole raw condition is `whereRaw()`.
:::

### Comparing to null

`where()` reads a null value as a null test, because `column = NULL` is never
true and is never what the caller meant:

```php
$database->table('users')->where('deleted_at', '=', null);   // WHERE deleted_at IS NULL
$database->table('users')->where('deleted_at', '!=', null);  // WHERE deleted_at IS NOT NULL
```

Any other operator with a null value throws, rather than compiling a comparison
that cannot match:

```php
$database->table('users')->where('age', '>', null);
// InvalidArgumentException: Operator [GreaterThan (>)] cannot be used with NULL.
```

:::tip
`whereNull()` says the same thing without depending on that reading. Prefer it
when the value is a literal null; `where()` earns its keep when the value is a
variable that might be null.
:::

### Empty membership tests

An empty `whereIn()` cannot compile to `IN ()`, which is a syntax error on most
databases. It compiles to a constant instead, which is what the condition
actually means:

```php
$database->table('users')->whereIn('id', []);      // WHERE 1 = 0 — matches nothing
$database->table('users')->whereNotIn('id', []);   // WHERE 1 = 1 — matches everything
```

:::caution
`whereNotIn('id', [])` matching every row is correct but easy to walk into with
a filter that came back empty. Check the list before you build the query if an
empty filter should mean "no results".
:::

### Grouping conditions

`whereNested()` receives a builder for the same table and wraps whatever it adds
in parentheses:

```php
$database->table('users')
    ->where('active', '=', 1)
    ->whereNested(static function (QueryBuilder $query): void {
        $query->where('role', '=', 'admin')->orWhere('role', '=', 'owner');
    });

// WHERE `active` = ? AND (`role` = ? OR `role` = ?)
```

A callback that adds no condition adds no parentheses, so a group built from an
optional filter disappears when the filter is empty.

### Raw conditions

`whereRaw()` and `orWhereRaw()` take a whole condition, with its own bindings:

```php
$database->table('users')
    ->where('active', '=', 1)
    ->orWhereRaw('name ILIKE ?', ['ada%']);

// WHERE `active` = ? OR (name ILIKE ?)
```

The fragment is wrapped in parentheses. That matters more than it looks: `AND`
binds tighter than `OR`, so without them a fragment containing its own `OR`
would re-associate and quietly mean something else.

```php
->where('x', '=', 1)->whereRaw('a = ? OR b = ?', [2, 3]);

// WHERE `x` = ? AND (a = ? OR b = ?)     with the parentheses
// WHERE `x` = ? AND a = ? OR b = ?       without them: (x AND a) OR b
```

:::danger
Nothing in the fragment is escaped. Put `?` in the SQL and pass values in the
bindings array; never concatenate input into it.
:::

### Subqueries

`whereExists()` takes another builder. `newQuery()` gives you one on the same
connection and grammar, so a function that receives only a builder can still
build a correlated subquery:

```php
$users = $database->table('users');

$users->whereExists(
    $users->newQuery('posts')
        ->select('id')
        ->whereColumn('posts.user_id', '=', 'users.id')
        ->where('views', '>', 10),
);
```

The subquery's bindings are interleaved at the position its `EXISTS` appears, so
conditions before and after it keep their values.

:::note
Ordering inside a subquery is dropped unless the subquery is also limited,
because ordering rows that only need to exist changes nothing — and SQL Server
rejects it outright.
:::

## Joining tables

```php
$database->table('users')
    ->join('posts', 'users.id', '=', 'posts.user_id')
    ->leftJoin('teams', 'users.team_id', '=', 'teams.id');
```

`join()` takes a `JoinType` as its fifth argument, defaulting to
`JoinType::Inner`. `leftJoin()` and `rightJoin()` are shorthands.

| Case | Emits |
| --- | --- |
| `JoinType::Inner` | `INNER JOIN` |
| `JoinType::Left` | `LEFT JOIN` |
| `JoinType::Right` | `RIGHT JOIN` |
| `JoinType::Full` | `FULL JOIN` — [not supported by MySQL](grammars.md#joins) |

Each join carries one condition. There is no `CROSS JOIN`, no `NATURAL JOIN`,
and no multi-condition `ON` yet.

## Grouping and filtering groups

```php
$database->table('users')
    ->select('role')
    ->selectRaw('COUNT(*) AS total')
    ->groupBy('role')
    ->having(new RawExpression('COUNT(*)'), '>', 1);
```

`groupBy()` appends, so repeated calls accumulate. `groupByRaw()` appends a raw
fragment.

The `having` family mirrors the `where` family, since both compile the same
clauses:

| Method | Compiles to |
| --- | --- |
| `having($column, $operator, $value)` | `column op ?` |
| `havingNull($column)` / `havingNotNull($column)` | `column IS NULL` / `IS NOT NULL` |
| `havingRaw(string $sql, array $bindings = [])` | The fragment as written, in parentheses |

Each has an `or` form. `having()` takes the same operators as `where()`.

```php
$database->table('users')
    ->select('role')
    ->groupBy('role')
    ->havingRaw('COUNT(*) > ?', [1])
    ->orHavingNull('role');

// GROUP BY `role` HAVING (COUNT(*) > ?) OR `role` IS NULL
```

:::caution
`having()` rejects a null value, because `HAVING total = NULL` is never true and
an exception is more useful than an empty result set. Use `havingNull()` when a
null test is what you want.
:::

## Ordering and paging

```php
$database->table('users')
    ->orderBy('name')
    ->orderByDesc('created_at')
    ->orderByRaw('FIELD(status, ?, ?)', ['live', 'draft'])
    ->limit(20)
    ->offset(40);
```

`orderBy()` takes an `OrderDirection`, defaulting to `Ascending`. `orderByDesc()`
is a shorthand. `orderByRaw()` takes a direction as its third argument, since a
raw fragment often carries its own.

`limit()` and `offset()` reject negative values. Every database spells
offset-without-limit differently; the grammar handles it, and
[Grammars](grammars.md#paging) shows what each one emits.

## Running it

| Method | Returns |
| --- | --- |
| `get()` | `list<array<string, mixed>>` — every row. |
| `first()` | `array<string, mixed>` or `null` — applies `LIMIT 1`. |
| `cursor()` | `iterable` — rows one at a time. |
| `exists()` | `bool` |
| `count(string\|Expression $column = '*')` | `int` |
| `sum($column)`, `avg($column)`, `min($column)`, `max($column)` | `string\|int\|float\|bool\|null` |

```php
$rows = $database->table('users')->where('active', '=', 1)->get();
$user = $database->table('users')->where('id', '=', 7)->first();
$total = $database->table('users')->count();

foreach ($database->table('logs')->cursor() as $row) {
    // one row at a time, never the whole table in memory
}
```

`first()` applies its limit to a copy, so the builder it was called on keeps
whatever limit you gave it.

:::note
`cursor()` is a generator: nothing is compiled or sent until you start iterating,
so an exception from the query surfaces at the first `foreach`, not at the call.
:::

`count()` drops ordering and paging, since neither changes a count. Over a
grouped or `distinct()` query it counts the rows the query returns, by wrapping
it in a derived table.

## Aggregates

`sum()`, `avg()`, `min()` and `max()` reduce the query to one value, applying its
conditions and joins:

```php
$total = $database->table('orders')->where('status', '=', 'paid')->sum('amount');

$oldest = $database->table('users')->min('created_at');
```

Like `count()`, they drop ordering and paging. With `distinct()` they aggregate
the distinct values — `SUM(DISTINCT amount)`.

:::caution
The return type is whatever the driver hands back, which is not the same across
databases. `sum()` over an integer column returns an `int` on SQLite and
PostgreSQL and a numeric `string` on MySQL and SQL Server; `avg()` returns a
`float` on SQLite and a `string` elsewhere. Nothing is cast, because casting a
`DECIMAL` sum to `float` would lose precision silently. Cast at the call site
once you know the column's type.
:::

:::note
`avg()` follows the column's type, not the average's. Over an `INT` column, SQL
Server returns an integer — `16` where the other three return `16.67`. Cast the
column in the query if you need the fraction:
`avg(new RawExpression('CAST(amount AS FLOAT)'))`.
:::

An aggregate over a grouped query is refused, because it has one value per group
rather than one value:

```php
$database->table('orders')->groupBy('status')->sum('amount');
// LogicException: A grouped query has one SUM per group; add it to the selection instead.
```

Select it instead, and read the rows:

```php
$database->table('orders')
    ->select('status')
    ->selectRaw('SUM(amount) AS total')
    ->groupBy('status')
    ->get();
```

`count()` is the exception: counting the groups of a grouped query is a
meaningful single number, so it is allowed.

## Inspecting without running

```php
$builder = $database->table('users')->where('active', '=', 1);

$builder->toSql();          // SELECT * FROM `users` WHERE `active` = ?
$builder->bindings();       // [1]
$builder->compile();        // CompiledQuery { sql, bindings }
$builder->toSelectQuery();  // the SelectQuery the grammar will compile
```

These are the same objects the terminal methods use, so what you see is what
would run.
