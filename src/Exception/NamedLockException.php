<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use Throwable;
use PDOException;
use RuntimeException;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Connection\Pdo\PdoError;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function implode;
use function sprintf;
use function array_map;
use function array_merge;

final class NamedLockException extends RuntimeException implements DatabaseException
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

    public static function alreadyHeld(ConnectionConfig $config, string $name): self
    {
        return new self(
            message: sprintf(
                'Connection "%s" already holds the named lock "%s"; named locks are not reentrant.',
                self::printable($config->name),
                self::printable($name),
            ),
            context: self::describe($config, Operation::AcquireLock, $name),
        );
    }

    public static function acquireFailed(ConnectionConfig $config, string $name, ?PDOException $previous = null): self
    {
        return new self(
            message: sprintf(
                'Unable to acquire the named lock "%s" on connection "%s"%s.',
                self::printable($name),
                self::printable($config->name),
                $previous === null ? '' : PdoError::suffix($previous),
            ),
            context: self::describe($config, Operation::AcquireLock, $name, $previous),
            previous: $previous,
        );
    }

    public static function releaseFailed(ConnectionConfig $config, string $name, ?PDOException $previous = null): self
    {
        return new self(
            message: sprintf(
                'Unable to release the named lock "%s" on connection "%s"%s; the connection still counts it as held.',
                self::printable($name),
                self::printable($config->name),
                $previous === null ? '' : PdoError::suffix($previous),
            ),
            context: self::describe($config, Operation::ReleaseLock, $name, $previous),
            previous: $previous,
        );
    }

    public static function alreadyReleased(string $name): self
    {
        return new self(
            message: sprintf('The named lock "%s" has already been released.', self::printable($name)),
            context: ['operation' => Operation::ReleaseLock->value, 'lock' => $name],
        );
    }

    /**
     * @param list<string> $names
     */
    public static function heldOnDisconnect(ConnectionConfig $config, array $names): self
    {
        return new self(
            message: sprintf(
                'Unable to disconnect connection "%s" while it holds the named locks %s; release them first.',
                self::printable($config->name),
                implode(', ', array_map(static fn(string $name): string => '"' . self::printable($name) . '"', $names)),
            ),
            context: array_merge($config->diagnostics(), [
                'operation' => Operation::Disconnect->value,
                'locks' => $names,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function describe(
        ConnectionConfig $config,
        Operation $operation,
        string $name,
        ?PDOException $cause = null,
    ): array {
        return array_merge(
            $config->diagnostics(),
            ['operation' => $operation->value, 'lock' => $name],
            $cause === null ? [] : PdoError::describe($cause),
        );
    }
}
