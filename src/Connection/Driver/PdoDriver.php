<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use PDOException;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Connection\Pdo\PdoError;
use Dirthara\Database\Connection\ValueObjects\Charset;
use Dirthara\Database\Connection\ValueObjects\DsnValue;
use Dirthara\Database\Connection\ValueObjects\DsnParameter;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Exceptions\ConnectionException;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;

use function trim;
use function sprintf;
use function array_merge;
use function array_replace;

abstract class PdoDriver implements Driver
{
    /**
     * @var array<int, mixed>
     */
    private const array DEFAULT_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];

    public function __construct(
        private readonly TransactionGrammar $grammar,
    ) {}

    public function transactionGrammar(): TransactionGrammar
    {
        return $this->grammar;
    }

    /**
     * @throws ConnectionException
     */
    public function connect(ConnectionConfig $config): PDO
    {
        try {
            return $this->createConnection($config);
        } catch (PDOException $exception) {
            throw new ConnectionException(
                message: $exception->getMessage(),
                code: PdoError::code($exception),
                previous: $exception,
                context: $this->context($config, cause: $exception),
            );
        }
    }

    /**
     * @throws ConnectionException
     */
    abstract protected function createConnection(ConnectionConfig $config): PDO;

    /**
     * @return array<int, mixed>
     */
    protected function options(ConnectionConfig $config): array
    {
        return array_replace(self::DEFAULT_OPTIONS, $config->options);
    }

    /**
     * @return array<string, mixed>
     */
    protected function context(
        ConnectionConfig $config,
        Operation $operation = Operation::Connect,
        ?PDOException $cause = null,
    ): array {
        return array_merge($config->diagnostics(), ['operation' => $operation->value], PdoError::describe($cause));
    }

    /**
     * @throws ConnectionException
     */
    protected function requireHost(ConnectionConfig $config): DsnValue
    {
        $host = $config->host === null ? '' : trim($config->host);

        if ($host === '') {
            throw new ConnectionException(
                sprintf('%s requires a nonempty host.', $this->name()->name),
                context: $this->context($config),
            );
        }

        return $this->dsnValue('host', $host, $config);
    }

    /**
     * @throws ConnectionException
     */
    protected function optionalDatabase(ConnectionConfig $config): ?DsnValue
    {
        $database = $config->database === null ? '' : trim($config->database);

        if ($database === '') {
            return null;
        }

        return $this->dsnValue('database', $database, $config);
    }

    /**
     * @throws ConnectionException
     */
    protected function charset(ConnectionConfig $config): ?Charset
    {
        if ($config->charset === null) {
            return null;
        }

        try {
            return new Charset($config->charset);
        } catch (ConnectionException $exception) {
            throw $exception->addContext($this->context($config));
        }
    }

    /**
     * The configured driver-specific parameters, ready to append to a DSN.
     *
     * @throws ConnectionException
     */
    protected function dsnParameters(ConnectionConfig $config): string
    {
        $dsn = '';

        foreach ($config->dsn as $name => $value) {
            try {
                $parameter = new DsnParameter($name, (string) $value);
            } catch (ConnectionException $exception) {
                throw $exception->addContext($this->context($config));
            }

            $dsn .= ';' . $parameter->toDsn();
        }

        return $dsn;
    }

    /**
     * @throws ConnectionException
     */
    protected function rejectDsnParameters(ConnectionConfig $config): void
    {
        if ($config->dsn !== []) {
            throw new ConnectionException(
                sprintf('%s does not support driver-specific DSN parameters.', $this->name()->name),
                context: $this->context($config),
            );
        }
    }

    /**
     * @throws ConnectionException
     */
    protected function rejectCharset(ConnectionConfig $config): void
    {
        if ($config->charset !== null) {
            throw new ConnectionException(
                sprintf('%s does not support a configurable charset.', $this->name()->name),
                context: $this->context($config),
            );
        }
    }

    /**
     * @throws ConnectionException
     */
    private function dsnValue(string $field, string $value, ConnectionConfig $config): DsnValue
    {
        try {
            return new DsnValue($field, $value);
        } catch (ConnectionException $exception) {
            throw $exception->addContext($this->context($config));
        }
    }
}
