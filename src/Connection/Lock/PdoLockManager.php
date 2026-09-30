<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Lock;

use PDO;
use Closure;
use PDOException;
use Dirthara\Database\Exception\NamedLockException;
use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Exception\InvalidLockNameException;
use Dirthara\Database\Exception\UnsupportedLockException;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function is_int;
use function is_array;
use function array_keys;
use function is_numeric;
use function array_key_exists;

final class PdoLockManager implements LockManager
{
    /**
     * @var array<string, true>
     */
    private array $held = [];

    /**
     * @param Closure(): PDO $pdo
     */
    public function __construct(
        private readonly Closure $pdo,
        private readonly ?NamedLockGrammar $grammar,
        private readonly ConnectionConfig $config,
    ) {}

    /**
     * @throws NamedLockException
     * @throws ConnectionException
     * @throws InvalidLockNameException
     * @throws UnsupportedLockException
     */
    public function acquire(string $name): AcquiredLock
    {
        return (
            $this->obtain($name, static fn(NamedLockGrammar $grammar): string => $grammar->acquire()) ?? throw NamedLockException::acquireFailed(
                $this->config,
                $name,
            )
        );
    }

    /**
     * @throws NamedLockException
     * @throws ConnectionException
     * @throws InvalidLockNameException
     * @throws UnsupportedLockException
     */
    public function tryAcquire(string $name): ?AcquiredLock
    {
        return $this->obtain($name, static fn(NamedLockGrammar $grammar): string => $grammar->tryAcquire());
    }

    /**
     * @return list<string>
     */
    public function held(): array
    {
        return array_keys($this->held);
    }

    /**
     * @param Closure(NamedLockGrammar): string $statement
     *
     * @throws NamedLockException
     * @throws ConnectionException
     * @throws InvalidLockNameException
     * @throws UnsupportedLockException
     */
    private function obtain(string $name, Closure $statement): ?AcquiredLock
    {
        if ($name === '') {
            throw InvalidLockNameException::empty();
        }

        $grammar = $this->grammar ?? throw UnsupportedLockException::namedLocksUnsupported($this->config->driver->name);

        if (array_key_exists($name, $this->held)) {
            throw NamedLockException::alreadyHeld($this->config, $name);
        }

        $session = ($this->pdo)();

        if ($session->getAttribute(PDO::ATTR_PERSISTENT) === true) {
            throw UnsupportedLockException::persistentSession($this->config);
        }

        $resource = $grammar->resource($this->config, $name);

        try {
            $outcome = $grammar->outcome($this->run($session, $statement($grammar), $resource));
        } catch (PDOException $exception) {
            throw NamedLockException::acquireFailed($this->config, $name, $exception);
        }

        if ($outcome === NamedLockOutcome::Failed) {
            throw NamedLockException::acquireFailed($this->config, $name);
        }

        if ($outcome === NamedLockOutcome::Contended) {
            return null;
        }

        $this->held[$name] = true;

        return new AcquiredLock($name, fn() => $this->release($name, $session, $grammar, $resource));
    }

    /**
     * @throws NamedLockException
     */
    private function release(string $name, PDO $session, NamedLockGrammar $grammar, int|string $resource): void
    {
        try {
            $outcome = $grammar->outcome($this->run($session, $grammar->release(), $resource));
        } catch (PDOException $exception) {
            throw NamedLockException::releaseFailed($this->config, $name, $exception);
        }

        if ($outcome !== NamedLockOutcome::Granted) {
            throw NamedLockException::releaseFailed($this->config, $name);
        }

        unset($this->held[$name]);
    }

    private function run(PDO $session, string $sql, int|string $resource): ?int
    {
        $statement = $session->prepare($sql);

        if ($statement === false) {
            return null;
        }

        $statement->bindValue(1, $resource, is_int($resource) ? PDO::PARAM_INT : PDO::PARAM_STR);

        if ($statement->execute() === false) {
            return null;
        }

        /** @var array<string, mixed>|false $row */
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        $statement->closeCursor();

        if (!is_array($row) || !array_key_exists('outcome', $row) || !is_numeric($row['outcome'])) {
            return null;
        }

        return (int) $row['outcome'];
    }
}
