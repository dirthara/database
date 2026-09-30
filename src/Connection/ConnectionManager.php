<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Exception\TransactionException;
use Dirthara\Database\Exception\ConnectionRegistryException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function array_keys;
use function array_key_exists;

final class ConnectionManager
{
    /** @var array<string, ConnectionConfig> */
    private readonly array $configs;

    /** @var array<string, Connection> */
    private array $connections = [];

    /**
     * @param iterable<ConnectionConfig> $configs
     *
     * @throws ConnectionException
     */
    public function __construct(
        private readonly ConnectionFactory $factory,
        iterable $configs,
        private readonly string $default = 'default',
    ) {
        $registeredConfigs = [];

        foreach ($configs as $config) {
            if (array_key_exists($config->name, $registeredConfigs)) {
                throw ConnectionRegistryException::duplicateConnection($config->name);
            }

            $registeredConfigs[$config->name] = $config;
        }

        $this->configs = $registeredConfigs;
    }

    /**
     * @throws ConnectionException
     */
    public function connection(?string $name = null): Connection
    {
        $name ??= $this->default;

        return $this->connections[$name] ??= $this->factory->create($this->config($name));
    }

    /**
     * @throws TransactionException
     */
    public function disconnect(?string $name = null): void
    {
        $name ??= $this->default;

        $connection = $this->connections[$name] ?? null;

        if ($connection === null) {
            return;
        }

        $connection->disconnect();

        unset($this->connections[$name]);
    }

    /**
     * @throws TransactionException
     */
    public function disconnectAll(): void
    {
        foreach (array_keys($this->connections) as $name) {
            $this->disconnect($name);
        }
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->configs);
    }

    /**
     * @throws ConnectionException
     */
    private function config(string $name): ConnectionConfig
    {
        return (
            $this->configs[$name] ?? throw ConnectionRegistryException::unknownConnection(
                $name,
                array_keys($this->configs),
            )
        );
    }
}
