<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Driver;

use PDO;
use PDOException;
use Dirthara\Database\Connection\Operation;
use Dirthara\Database\Connection\Pdo\PdoError;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Connection\ValueObjects\Charset;
use Dirthara\Database\Connection\Lock\NamedLockGrammar;
use Dirthara\Database\Connection\ValueObjects\DsnValue;
use Dirthara\Database\Connection\ValueObjects\DsnParameter;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;
use Dirthara\Database\Connection\Transaction\TransactionGrammar;
use Dirthara\Database\Exception\InvalidConnectionConfigException;

use function trim;
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

    public function namedLockGrammar(): ?NamedLockGrammar
    {
        return null;
    }

    /**
     * @throws ConnectionException
     */
    public function connect(ConnectionConfig $config): PDO
    {
        try {
            return $this->createConnection($config);
        } catch (PDOException $exception) {
            throw ConnectionException::connectFailed($config, $exception);
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
        return array_merge(
            $config->diagnostics(),
            ['operation' => $operation->value],
            $cause === null ? [] : PdoError::describe($cause),
        );
    }

    /**
     * @throws ConnectionException
     */
    protected function requireHost(ConnectionConfig $config): DsnValue
    {
        $host = $config->host === null ? '' : trim($config->host);

        if ($host === '') {
            throw InvalidConnectionConfigException::missingHost($config);
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
        } catch (InvalidConnectionConfigException $exception) {
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
            } catch (InvalidConnectionConfigException $exception) {
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
            throw InvalidConnectionConfigException::unsupportedDsnParameters($config);
        }
    }

    /**
     * @throws ConnectionException
     */
    protected function rejectCharset(ConnectionConfig $config): void
    {
        if ($config->charset !== null) {
            throw InvalidConnectionConfigException::unsupportedCharset($config);
        }
    }

    /**
     * @throws ConnectionException
     */
    private function dsnValue(string $field, string $value, ConnectionConfig $config): DsnValue
    {
        try {
            return new DsnValue($field, $value);
        } catch (InvalidConnectionConfigException $exception) {
            throw $exception->addContext($this->context($config));
        }
    }
}
