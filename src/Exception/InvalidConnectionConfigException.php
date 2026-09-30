<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use InvalidArgumentException;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function sprintf;
use function array_merge;

final class InvalidConnectionConfigException extends InvalidArgumentException implements DatabaseException
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

    public static function missingHost(ConnectionConfig $config): self
    {
        return new self(
            message: sprintf('The %s driver requires a nonempty host.', $config->driver->name),
            context: self::connecting($config),
        );
    }

    public static function missingDatabase(ConnectionConfig $config): self
    {
        return new self(
            message: sprintf('The %s driver requires an explicit database.', $config->driver->name),
            context: self::connecting($config),
        );
    }

    public static function unsupportedDsnParameters(ConnectionConfig $config): self
    {
        return new self(
            message: sprintf('The %s driver does not support driver-specific DSN parameters.', $config->driver->name),
            context: self::connecting($config),
        );
    }

    public static function unsupportedCharset(ConnectionConfig $config): self
    {
        return new self(
            message: sprintf('The %s driver does not support a configurable charset.', $config->driver->name),
            context: self::connecting($config),
        );
    }

    public static function invalidCharset(string $charset): self
    {
        return new self(
            message: sprintf('The configured charset "%s" is not a valid identifier.', self::printable($charset)),
            context: ['charset' => $charset],
        );
    }

    public static function semicolonInDsnValue(string $field): self
    {
        return new self(
            message: sprintf('The configured %s must not contain a semicolon.', self::printable($field)),
            context: ['field' => $field],
        );
    }

    public static function invalidDsnParameterName(string $parameter): self
    {
        return new self(
            message: sprintf('The DSN parameter name "%s" must be an identifier.', self::printable($parameter)),
            context: ['parameter' => $parameter],
        );
    }

    public static function invalidSavepointPrefix(string $prefix): self
    {
        return new self(
            message: sprintf(
                'The savepoint prefix "%s" must start with a letter or underscore and contain only letters, digits, and '
                . 'underscores.',
                self::printable($prefix),
            ),
            context: ['prefix' => $prefix],
        );
    }

    public static function savepointPrefixTooLong(string $prefix, int $maximum): self
    {
        return new self(message: sprintf('The savepoint prefix must not exceed %d characters.', $maximum), context: [
            'prefix' => $prefix,
            'maximum' => $maximum,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function connecting(ConnectionConfig $config): array
    {
        return array_merge($config->diagnostics(), ['operation' => Operation::Connect->value]);
    }
}
