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

final class ConnectionException extends RuntimeException implements DatabaseException
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

    public static function connectFailed(ConnectionConfig $config, PDOException $previous): self
    {
        return new self(
            message: sprintf(
                'Unable to connect to the %s database of connection "%s"%s.',
                $config->driver->name,
                self::printable($config->name),
                PdoError::suffix($previous),
            ),
            context: array_merge(
                $config->diagnostics(),
                ['operation' => Operation::Connect->value],
                PdoError::describe($previous),
            ),
            previous: $previous,
        );
    }
}
