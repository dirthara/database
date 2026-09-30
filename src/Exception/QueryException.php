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

final class QueryException extends RuntimeException implements DatabaseException
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

    public static function prepareRefused(ConnectionConfig $config, string $query): self
    {
        return new self(
            message: sprintf(
                'The database refused to prepare a query on connection "%s".',
                self::printable($config->name),
            ),
            context: self::describe($config, Operation::Prepare, ['query' => $query]),
        );
    }

    public static function executeRefused(ConnectionConfig $config, string $query): self
    {
        return new self(
            message: sprintf(
                'The database refused to execute a query on connection "%s".',
                self::printable($config->name),
            ),
            context: self::describe($config, Operation::Execute, ['query' => $query]),
        );
    }

    public static function executeFailed(ConnectionConfig $config, string $query, PDOException $previous): self
    {
        return new self(
            message: sprintf(
                'Unable to execute a query on connection "%s"%s.',
                self::printable($config->name),
                PdoError::suffix($previous),
            ),
            context: self::describe($config, Operation::Execute, ['query' => $query], $previous),
            previous: $previous,
        );
    }

    public static function lastInsertIdFailed(ConnectionConfig $config, PDOException $previous): self
    {
        return new self(
            message: sprintf(
                'Unable to read the last inserted id on connection "%s"%s.',
                self::printable($config->name),
                PdoError::suffix($previous),
            ),
            context: self::describe($config, Operation::LastInsertId, cause: $previous),
            previous: $previous,
        );
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    private static function describe(
        ConnectionConfig $config,
        Operation $operation,
        array $extra = [],
        ?PDOException $cause = null,
    ): array {
        return array_merge(
            $config->diagnostics(),
            ['operation' => $operation->value],
            $extra,
            $cause === null ? [] : PdoError::describe($cause),
        );
    }
}
