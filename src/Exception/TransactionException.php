<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use Throwable;
use PDOException;
use RuntimeException;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Connection\Pdo\PdoError;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function sprintf;
use function array_merge;

final class TransactionException extends RuntimeException implements DatabaseException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(string $message, array $context, ?Throwable $previous = null)
    {
        parent::__construct($message, previous: $previous);

        $this->context = $context;
    }

    public static function failed(ConnectionConfig $config, Operation $operation, PDOException $previous): self
    {
        return new self(
            message: sprintf(
                'Unable to %s on connection "%s"%s.',
                $operation->describe(),
                self::printable($config->name),
                PdoError::suffix($previous),
            ),
            context: array_merge(self::describe($config, $operation), PdoError::describe($previous)),
            previous: $previous,
        );
    }

    public static function refused(ConnectionConfig $config, Operation $operation): self
    {
        return new self(
            message: sprintf(
                'The database refused to %s on connection "%s".',
                $operation->describe(),
                self::printable($config->name),
            ),
            context: self::describe($config, $operation),
        );
    }

    public static function noActiveTransaction(ConnectionConfig $config, Operation $operation): self
    {
        return new self(
            message: sprintf(
                'Unable to %s on connection "%s": there is no active transaction.',
                $operation->describe(),
                self::printable($config->name),
            ),
            context: self::describe($config, $operation),
        );
    }

    public static function activeOnDisconnect(ConnectionConfig $config): self
    {
        return new self(
            message: sprintf(
                'Unable to disconnect connection "%s" while a transaction is active.',
                self::printable($config->name),
            ),
            context: self::describe($config, Operation::Disconnect),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function describe(ConnectionConfig $config, Operation $operation): array
    {
        return array_merge($config->diagnostics(), ['operation' => $operation->value]);
    }
}
