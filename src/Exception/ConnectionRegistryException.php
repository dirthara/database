<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use InvalidArgumentException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function sprintf;

final class ConnectionRegistryException extends InvalidArgumentException implements DatabaseException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(string $message, array $context)
    {
        parent::__construct($message);

        $this->context = $context;
    }

    public static function duplicateDriver(string $driver): self
    {
        return new self(
            message: sprintf('The database driver "%s" is registered more than once.', self::printable($driver)),
            context: ['driver' => $driver],
        );
    }

    public static function unregisteredDriver(ConnectionConfig $config): self
    {
        return new self(
            message: sprintf(
                'The database driver "%s" requested by connection "%s" is not registered.',
                $config->driver->value,
                self::printable($config->name),
            ),
            context: $config->diagnostics(),
        );
    }

    public static function duplicateConnection(string $connection): self
    {
        return new self(
            message: sprintf(
                'The database connection "%s" is configured more than once.',
                self::printable($connection),
            ),
            context: ['connection' => $connection],
        );
    }

    /**
     * @param list<string> $configured
     */
    public static function unknownConnection(string $connection, array $configured): self
    {
        return new self(
            message: sprintf('The database connection "%s" is not configured.', self::printable($connection)),
            context: ['connection' => $connection, 'configured' => $configured],
        );
    }
}
