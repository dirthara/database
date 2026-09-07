<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Connection\Transaction\TransactionManager;

final readonly class ConnectionFactory
{
    /**
     * @param iterable<DriverName, Driver> $drivers
     */
    public function __construct(
        private iterable $drivers,
    ) {}

    public function create(ConnectionConfig $config): Connection
    {
        $driver = $this->drivers[$config->driver->value];

        return new PdoConnection(config: $config, driver: $driver, transactions: new TransactionManager());
    }
}
