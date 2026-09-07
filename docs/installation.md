---
id: installation
title: Installation
sidebar_position: 2
description: Install the package with Composer and enable the PDO extension for your database.
---

# Installation

Install the package with Composer:

```sh
composer require dirthara/database
```

## Requirements

| Requirement | Version |
| --- | --- |
| PHP | 8.5 or newer |
| `ext-pdo` | Required |

## Driver extensions

PDO itself is the shared interface; each database needs its own PDO driver
extension. Install only the ones you connect to.

| Database | Extension | `DriverName` |
| --- | --- | --- |
| MySQL and MariaDB | `pdo_mysql` | `DriverName::MySql` |
| PostgreSQL | `pdo_pgsql` | `DriverName::PostgresSql` |
| SQLite | `pdo_sqlite` | `DriverName::SQLite` |
| SQL Server | `pdo_sqlsrv` | `DriverName::SqlServer` |

Check what your runtime has:

```sh
php -r 'print_r(PDO::getAvailableDrivers());'
```

A missing extension is not reported when you register the driver. It surfaces as
a [`ConnectionException`](error-handling.md) the first time a query runs, because
that is when PDO is constructed.

## Autoloading

The package autoloads `Dirthara\Database\` from `src/` under PSR-4. Everything in
these docs is imported from that namespace, for example:

```php
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
```
