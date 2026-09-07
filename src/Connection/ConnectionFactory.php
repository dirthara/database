<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;

final readonly class ConnectionFactory
{
    /**
     * @var array<string, Driver>
     */
    private array $drivers;

    /**
     * @param iterable<Driver> $drivers
     */
    public function __construct(
        iterable $drivers,
        private TransactionGrammar $transactionGrammar,
    ) {
        $registeredDrivers = [];

        foreach ($drivers as $driver) {
            $registeredDrivers[$driver->name()->value] = $driver;
        }

        $this->drivers = $registeredDrivers;
    }

    /**
     * @throws ConnectionException
     */
    public function create(ConnectionConfig $config): Connection
    {
        $driver =
            $this->drivers[$config->driver->value] ?? throw new ConnectionException('The requested database driver is not registered.', context: [
                'driver' => $config->driver->value,
            ]);

        return new PdoConnection(config: $config, driver: $driver, transactionGrammar: $this->transactionGrammar);
    }
}
