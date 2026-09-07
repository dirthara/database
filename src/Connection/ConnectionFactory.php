<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Connection\Driver\Driver;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

use function array_reverse;
use function array_key_exists;
use function iterator_to_array;

final readonly class ConnectionFactory
{
    /**
     * @var array<string, Driver>
     */
    private array $drivers;

    /**
     * @var list<ConnectionMiddleware>
     */
    private array $middleware;

    /**
     * @param iterable<Driver> $drivers
     * @param iterable<ConnectionMiddleware> $middleware
     *
     * @throws ConnectionException
     */
    public function __construct(iterable $drivers, iterable $middleware = [])
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
        $this->middleware = iterator_to_array($middleware, preserve_keys: false);
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

        $connection = new PdoConnection(config: $config, driver: $driver);

        foreach (array_reverse($this->middleware) as $middleware) {
            $connection = $middleware->wrap($connection);
        }

        return $connection;
    }
}
