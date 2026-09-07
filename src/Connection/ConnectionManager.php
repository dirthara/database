<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

final class ConnectionManager
{
    /** @var array<string, Connection> */
    private array $connections = [];

    /**
     * @param array<string, ConnectionConfig> $configs
     */
    public function __construct(
        private readonly ConnectionFactory $factory,
        private readonly array $configs,
        private readonly string $default = 'default',
    ) {}

    public function connection(?string $name = null): Connection
    {
        $name ??= $this->default;

        return $this->connections[$name] ??= $this->factory->create($this->configs[$name]);
    }
}
