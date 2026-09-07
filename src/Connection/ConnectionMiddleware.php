<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

interface ConnectionMiddleware
{
    public function wrap(Connection $connection): Connection;
}
