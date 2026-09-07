<?php

namespace Dirthara\Database\Connection\Driver;

enum DriverName: string
{
    case MySql = 'mysql';
    case PostgresSql = 'pgsql';
    case SqlServer = 'sqlsrv';
    case SQLite = 'sqlite';
}
