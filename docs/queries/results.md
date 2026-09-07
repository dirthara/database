---
id: results
title: Reading results
sidebar_position: 2
description: The Result interface, its forward-only contract, and how to pick rows and columns.
---

# Reading results

`execute()` returns a `Result`. It wraps the executed statement and reads rows
from it.

```php
interface Result
{
    public function first(): ?array;

    public function all(): array;

    public function column(int|string $column = 0): array;

    public function affectedRows(): int;

    public function iterate(): iterable;
}
```

## The forward-only contract

:::caution
A result reads the rows once, moving forward only. Read one result with one
method.
:::

There is no rewind. The rows a method consumes are gone, so a second call sees
only what is left:

```php
$result = $connection->execute('SELECT * FROM users');

$first = $result->first(); // the first row
$rest = $result->all();    // every row *except* the first
```

That is occasionally what you want, and much more often a bug. Nothing throws,
because it is a legal thing to do with a cursor. If you need the rows twice,
call `all()` once and keep the array.

The reason for the contract is `iterate()`: a result that buffered everything up
front could not stream a large table. The cost is that all five methods share
one cursor.

## Rows

`first()` returns the next row as a string-keyed array, or null when there is
none. With a `LIMIT 1` query it is the row you asked for:

```php
$user = $connection->execute('SELECT * FROM users WHERE id = ?', [$id])->first();

if ($user === null) {
    throw new UserNotFound($id);
}
```

`all()` returns the remaining rows as a list. On an unread result, that is every
row:

```php
$users = $connection->execute('SELECT * FROM users')->all();
```

An empty result is an empty array, never null, so it is safe to iterate without
checking.

## Streaming

`iterate()` yields rows one at a time without buffering them, which is how you
walk a table that does not fit in memory:

```php
foreach ($connection->execute('SELECT * FROM events')->iterate() as $event) {
    $this->process($event);
}
```

It is a generator, so nothing is fetched until the loop asks. Breaking out early
leaves the rest of the rows unread on the connection; if you plan to reuse the
connection immediately, finish the loop or read the rest.

## A single column

`column()` fetches one column across all remaining rows, flattening the rows
away:

```php
$ids = $connection->execute('SELECT id FROM users WHERE role = ?', ['admin'])->column();

$names = $connection->execute('SELECT id, name FROM users')->column('name');
```

The argument is a position or a name, and it defaults to position `0` — the
first column of the select. Positions start at zero.

Selecting by name asks the driver for column metadata, which not every driver
exposes. Both failure modes throw a `ResultException`:

| Situation | Message |
| --- | --- |
| The position is out of range, or the name matches no column | The result set has no such column. Context lists the columns it does have. |
| The driver exposes no column metadata at all | A column cannot be selected by name on this driver. Use a position instead. |

An out-of-range position is caught before the fetch, so a typo is an exception
rather than an empty array.

## Affected rows

`affectedRows()` is the statement's row count. For a write, that is the number
of rows changed:

```php
$deleted = $connection->execute('DELETE FROM sessions WHERE expires_at < ?', [$cutoff])->affectedRows();
```

:::note
Do not use it to count rows in a select. PDO does not promise a row count for a
`SELECT`, and what you get varies by driver — often zero, sometimes the number
fetched so far. To count rows, either `count($result->all())` or ask the
database with `SELECT COUNT(*)`.
:::

Unlike the other methods, `affectedRows()` reads no rows, so it can be called
alongside one of them.

## Choosing a method

| You want | Method |
| --- | --- |
| At most one row | `first()` |
| Every row, as an array | `all()` |
| Every row, without loading them all | `iterate()` |
| One column across rows | `column()` |
| How many rows a write changed | `affectedRows()` |
