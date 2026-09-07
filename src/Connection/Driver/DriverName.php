<?php

namespace Dirthara\Database\Connection\Driver;

enum DriverName
{
    case MySql;
    case PostgresSql;
    case SqlServer;
    case SQLite;
}
