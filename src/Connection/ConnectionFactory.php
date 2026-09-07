<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

use function array_key_exists;

final readonly class ConnectionFactory
{
    /**
     * @var array<string, Driver>
     */
    private array $drivers;

    /**
     * @param iterable<Driver> $drivers
     *
     * @throws ConnectionException
     */
    public function __construct(iterable $drivers)
    {
        $registeredDrivers = [];

        foreach ($drivers as $driver) {
            $name = $driver->name()->value;

            if (array_key_exists($name, $registeredDrivers)) {
                throw new ConnectionException('The database driver is registered more than once.', context: [
                    'driver' => $name,
                ]);
            }

            $registeredDrivers[$name] = $driver;
        }

        $this->drivers = $registeredDrivers;
    }

    /**
     * @throws ConnectionException
     */
    public function create(ConnectionConfig $config): Connection
    {
        $driver = $this->drivers[$config->driver->value] ?? throw new ConnectionException(
            'The requested database driver is not registered.',
            context: $config->diagnostics(),
        );

        return new PdoConnection(config: $config, driver: $driver);
    }
}
