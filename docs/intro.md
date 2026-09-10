---
id: intro
title: Dirthara Database
sidebar_label: Introduction
sidebar_position: 1
description: A thin PDO layer with named connections, a query builder, forward-only results, and nested transactions.
---

# Dirthara Database

Dirthara Database is a thin layer over PDO. It is not an ORM: there are no
models, no relations, and no change tracking. It gives you the plumbing around a
query — resolving a named connection, binding parameters, reading a result,
nesting a transaction — and a query builder that compiles the same query for
MySQL, PostgreSQL, SQLite, and SQL Server.

SQL is never hidden. `Database::execute()` takes the statement you wrote, and
the builder will hand you its SQL and bindings whenever you ask.

## The pieces

| Piece | Role |
| --- | --- |
| [`Database`](database.md) | The entry point: resolves connections, opens builders, runs transactions. |
| [`ConnectedDatabase`](database.md#scoping-to-one-connection) | The same, bound to one connection. |
| [`QueryBuilder`](query-builder/building-queries.md) | Collects clauses and runs the query. |
| [`Expression`](query-builder/expressions.md) | A quoted name, a raw fragment, or either with an alias. |
| [`QueryGrammar`](query-builder/grammars.md) | Compiles a query into SQL and bindings for one database. |
| [`ConnectionConfig`](connections/configuration.md) | Describes one named connection: driver, host, credentials, options. |
| [`Driver`](connections/drivers.md) | Turns a config into a `PDO` instance and names the transaction grammar its database understands. |
| [`ConnectionFactory`](connections/connection-manager.md#the-factory) | Builds a `Connection` from a config using a registered driver, wrapping it in middleware. |
| [`ConnectionManager`](connections/connection-manager.md) | Resolves and caches connections by name. |
| [`Connection`](queries/executing-queries.md) | Runs queries and exposes the transaction manager. |
| [`Result`](queries/results.md) | Reads the rows a query returned. |
| [`TransactionManager`](transactions.md) | Commits, rolls back, and nests transactions with savepoints. |
| [`ConnectionMiddleware`](connections/middleware.md) | Wraps every connection the factory creates. |

Everything is an interface with one shipped implementation per database, so a
connection or a grammar can be decorated or replaced without reaching for PDO.

## What is public

The package is meant to be used through three things: the connection setup, the
`Database` entry point, and the query builder. Those, the expressions you hand
the builder, and the grammar seams are the public API.

| Public | |
| --- | --- |
| `Database`, `ConnectedDatabase` | The entry point. |
| `Connection`, `ConnectionManager`, `ConnectionFactory`, `Driver`, `Result`, `TransactionManager` | Connection setup and use. |
| `QueryBuilder` | Everything you call to build and run a query. |
| `Dirthara\Database\Query\Expression\*` | `Identifier`, `RawExpression`, `Aliased`, `ExpressionFactory`. |
| `Dirthara\Database\Query\Sql\*` | The enums a builder method accepts: `ComparisonOperator`, `JoinType`, `OrderDirection`, `AggregateFunction`. |
| `Dirthara\Database\Query\Grammar\*` | The `QueryGrammar` interface, `SqlQueryGrammar`, the four drivers, the resolver. |
| `CompiledQuery` | What `compile()` returns. |

Everything under `Query\Clause` and `Query\Queries` is marked `@internal`. How
a query is represented between the builder and the grammar is this package's own
business, and clause types get added as the builder grows — so those classes
change without a major version. Use `toSql()`, `bindings()`, or `compile()` to
see what a builder will run.

## Design notes

Connections are lazy. Building a `ConnectionConfig`, a `ConnectionFactory`, or a
`ConnectionManager` never opens a socket; PDO is constructed the first time a
query runs.

Nothing is configured through arrays of magic strings. Values that have rules —
a charset, a savepoint prefix, a DSN fragment, a column name — are value objects
that validate on construction, so a typo fails where it is written rather than
inside a DSN or a query.

The builder refuses what it cannot compile. A clause a database does not support
raises an exception while the query is being built, instead of being dropped from
the SQL — so a limited, ordered delete does not quietly turn into a delete of
everything. [Grammars](query-builder/grammars.md) lists what each database
accepts.

Every exception extends `DatabaseException` and carries structured context for a
PSR-3 logger. Credentials are never part of it. See
[Error handling](error-handling.md).

## Where to go next

- [Installation](installation.md) for the Composer requirement and extensions.
- [Getting started](getting-started.md) for a working connection in one file.
- [Database](database.md) for the entry point and how connections are scoped.
- [Building queries](query-builder/building-queries.md) for the builder's clauses.
- [Connection configuration](connections/configuration.md) for every option and
  what it means.
