<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Driver\DriverName;

class ConnectionConfig
{
    public function __construct(
        public DriverName $driver,
        public ?string $host = null,
        public ?int $port = null,
        public ?string $database = null,
        public ?string $username = null,
        #[\SensitiveParameter] public ?string $password = null,
        public ?string $charset = null,
        public array $options = [],
    ) {}
}
