---
id: expressions
title: Expressions
sidebar_position: 2
description: When a string is quoted as a name, when it is raw SQL, and how aliases and bindings work.
---

# Expressions

Every column, table, and group in a query is an `Expression`. It is a marker
interface with three implementations, and which one you get decides whether the
database sees a quoted name or the characters you wrote.

| Implementation | Compiles to | Use for |
| --- | --- | --- |
| `Identifier` | A quoted name, one segment per dot | Columns, tables, anything the database should look up by name. |
| `RawExpression` | The string as written, plus its own bindings | Functions, operators, anything the builder has no clause for. |
| `Aliased` | `<expression> AS <quoted alias>` | Naming either of the above. |

## Strings become identifiers

Anywhere a method takes `string|Expression`, a string is read by
`ExpressionFactory::from()`. It produces an `Identifier` — or an `Aliased` one
when it finds an alias — and **never** a `RawExpression`.

```php
$database->table('users')->select('name');            // SELECT `name`
$database->table('users')->select('users.name');      // SELECT `users`.`name`
$database->table('users')->select('users.*');         // SELECT `users`.*
$database->table('users')->select('name as who');     // SELECT `name` AS `who`
```

Each dot-separated segment is quoted independently, so a qualified name works
without you escaping anything, and `*` passes through as itself.

:::danger
A string is never read as SQL. That is deliberate: if `select()` promoted a
string containing `(` to raw SQL, then a column name that arrived from a request
would be emitted verbatim into the query. Raw SQL is always explicit.
:::

Because of that rule, a fragment passed as a string is caught rather than
quoted into nonsense:

```php
$database->table('users')->select('COUNT(*)');
// InvalidArgumentException: The identifier [COUNT(*)] looks like SQL rather
// than a name; use a raw expression instead.
```

`Identifier` rejects a name containing `(`, `)` or `,`, and a name with an empty
segment such as `users.` or `a..b`. It does not reject anything else, because a
quoted identifier may legitimately contain almost anything — `first name` is a
valid column on all four databases and is quoted correctly.

## Raw expressions

`RawExpression` is emitted exactly as written, and carries its own bindings:

```php
use Dirthara\Database\Query\Expression\RawExpression;

$database->table('users')
    ->select(new RawExpression('COALESCE(name, ?) AS name', ['unknown']))
    ->where('active', '=', 1);

// SELECT COALESCE(name, ?) AS name FROM `users` WHERE `active` = ?
// bindings: ['unknown', 1]
```

The bindings are interleaved in the order the SQL emits each clause — select,
then joins, then conditions, then groups, then havings, then ordering — so a
bound fragment in the select list keeps its values ahead of the conditions'.

Three shorthands exist for the common positions:

```php
$database->table('users')
    ->selectRaw('COUNT(*) AS total')
    ->groupByRaw('DATE(created_at)')
    ->orderByRaw('FIELD(status, ?, ?)', ['live', 'draft']);
```

A raw expression works in a condition's column position too:

```php
$database->table('users')->where(new RawExpression('LOWER(name)'), '=', 'ada');
```

:::caution
Nothing in a `RawExpression` is escaped or validated. Never build one by
concatenating input — put a `?` in the SQL and pass the value in the bindings
array, which is what the second argument is for.
:::

## Aliases

An alias can be parsed from a string or built explicitly. Both quote the alias:

```php
use Dirthara\Database\Query\Expression\Aliased;
use Dirthara\Database\Query\Expression\Identifier;

$database->table('users as u')->select('u.name');
$database->table(new Aliased(new Identifier('users'), 'u'))->select('u.name');

// FROM `users` AS `u`
```

Parsing splits on a whitespace-delimited `AS`, case-insensitively. A string with
two of them throws rather than guessing which one is the alias.

An alias makes a self-join possible, since both sides need their own name:

```php
$database->table('users as a')
    ->select('a.name')
    ->join('users as b', 'a.manager_id', '=', 'b.id');
```

`Aliased` wraps a raw expression too, which is the portable way to name a
computed column:

```php
$database->table('users')->select(new Aliased(new RawExpression('COUNT(*)'), 'total'));

// SELECT COUNT(*) AS `total`
```

:::note
An alias only makes sense where SQL allows one — a select column, a table, a
join. Nothing stops you passing an `Aliased` to `where()`, and nothing will
catch it, because every slot takes the same `Expression` type. The result is
invalid SQL from the database rather than an exception from here.
:::

## Building expressions yourself

`ExpressionFactory::from()` is what the builder calls, and it is safe to call
directly when you want to normalise a value once:

```php
use Dirthara\Database\Query\Expression\ExpressionFactory;

ExpressionFactory::from('users.name');      // Identifier
ExpressionFactory::from('users as u');      // Aliased(Identifier, 'u')
ExpressionFactory::from(new RawExpression('NOW()'));  // returned unchanged
```

An `Expression` passed in comes back as it is, so a value can go through the
factory more than once without changing.

## Table names

Tables are expressions too, which is what makes aliases work. The same rules
apply: a string is a name and gets quoted, so a table alias has to be an alias
rather than a hand-written fragment.

```php
$database->table('app.users');   // FROM `app`.`users`
$database->table('users as u');  // FROM `users` AS `u`
```

For a table-valued function or anything else a name cannot describe, use a raw
expression — and note it can carry bindings, which land before the conditions:

```php
$database->table(new RawExpression('history(?) AS h', ['2026-01-01']))
    ->select('h.name')
    ->where('h.active', '=', 1);

// FROM history(?) AS h WHERE `h`.`active` = ?
// bindings: ['2026-01-01', 1]
```

## A note on quoting and case

Quoting a name preserves its case, and PostgreSQL folds *unquoted* names to
lower case. A column created as `userId` is stored as `userid` unless it was
created quoted, so `select('userId')` compiles to `"userId"` and will not find
it. That is standard behaviour for a builder that quotes, not something this
package decides — but it is the difference that surprises people moving a query
from MySQL to PostgreSQL.
