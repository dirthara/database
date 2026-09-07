<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

enum DriverName: string
{
    case MySql = 'mysql';
    case PostgresSql = 'pgsql';
    case SqlServer = 'sqlsrv';
    case SQLite = 'sqlite';
}
