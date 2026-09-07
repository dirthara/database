<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\ValueObjects;

use SensitiveParameter;
use Dirthara\Database\Connection\Driver\DriverName;

use function array_filter;

final readonly class ConnectionConfig
{
    /**
     * @param array<int, mixed> $options
     * @param array<string, scalar> $dsn
     */
    public function __construct(
        public DriverName $driver,
        public string $name = 'default',
        public ?string $host = null,
        public ?int $port = null,
        public ?string $database = null,
        #[SensitiveParameter]
        public ?string $username = null,
        #[SensitiveParameter]
        public ?string $password = null,
        public ?string $charset = null,
        public array $options = [],
        public array $dsn = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        return array_filter(
            [
                'connection' => $this->name,
                'driver' => $this->driver->value,
                'host' => $this->host,
                'port' => $this->port,
                'database' => $this->database,
            ],
            static fn(mixed $value): bool => $value !== null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'name' => $this->name,
            'driver' => $this->driver->value,
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password === null ? null : '[redacted]',
            'charset' => $this->charset,
            'options' => $this->options,
            'dsn' => $this->dsn,
        ];
    }
}
