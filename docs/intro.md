---
id: intro
title: Dirthara Database
sidebar_label: Introduction
sidebar_position: 1
description: A thin PDO layer with named connections, forward-only results, and nested transactions.
---

# Dirthara Database

Dirthara Database is a thin layer over PDO. It does not hide SQL and it is not a
query builder or an ORM. It gives you the plumbing around a query: resolving a
named connection, binding parameters, reading a result, and nesting a
transaction.

## The pieces

| Piece | Role |
| --- | --- |
| [`ConnectionConfig`](connections/configuration.md) | Describes one named connection: driver, host, credentials, options. |
| [`Driver`](connections/drivers.md) | Turns a config into a `PDO` instance and names the transaction grammar its database understands. |
| [`ConnectionFactory`](connections/connection-manager.md#the-factory) | Builds a `Connection` from a config using a registered driver, wrapping it in middleware. |
| [`ConnectionManager`](connections/connection-manager.md) | Resolves and caches connections by name. |
| [`Connection`](queries/executing-queries.md) | Runs queries and exposes the transaction manager. |
| [`Result`](queries/results.md) | Reads the rows a query returned. |
| [`TransactionManager`](transactions.md) | Commits, rolls back, and nests transactions with savepoints. |
| [`ConnectionMiddleware`](connections/middleware.md) | Wraps every connection the factory creates. |

Everything is an interface with one shipped implementation, so a connection can
be decorated or replaced without reaching for PDO.

## Design notes

Connections are lazy. Building a `ConnectionConfig`, a `ConnectionFactory`, or a
`ConnectionManager` never opens a socket; PDO is constructed the first time a
query runs.

Nothing is configured through arrays of magic strings. Values that have rules —
a charset, a savepoint prefix, a DSN fragment — are value objects that validate
on construction, so a typo fails where it is written rather than inside a DSN.

Every exception extends `DatabaseException` and carries structured context for a
PSR-3 logger. Credentials are never part of it. See
[Error handling](error-handling.md).

## Where to go next

- [Installation](installation.md) for the Composer requirement and extensions.
- [Getting started](getting-started.md) for a working connection in one file.
- [Connection configuration](connections/configuration.md) for every option and
  what it means.
