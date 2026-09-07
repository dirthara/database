<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use Dirthara\Database\Connection\ConnectionConfig;

interface Driver
{
    public function name(): DriverName;

    public function connect(ConnectionConfig $config): PDO;
}
