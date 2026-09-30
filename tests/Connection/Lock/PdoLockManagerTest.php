<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\Lock;

use PDO;
use PDOException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Exception\NamedLockException;
use Dirthara\Database\Connection\Lock\PdoLockManager;
use Dirthara\Database\Connection\Lock\NamedLockGrammar;
use Dirthara\Database\Exception\InvalidLockNameException;
use Dirthara\Database\Exception\UnsupportedLockException;
use Dirthara\Database\Tests\Fixtures\ScriptedNamedLockGrammar;
use Dirthara\Database\Connection\ValueObjects\ConnectionConfig;

use function array_shift;

final class PdoLockManagerTest extends TestCase
{
    #[Test]
    public function it_acquires_a_named_lock(): void
    {
        $manager = $this->manager();

        $lock = $manager->acquire('alpha');

        self::assertSame('alpha', $lock->name);
        self::assertFalse($lock->released);
        self::assertSame(['alpha'], $manager->held());
    }

    #[Test]
    public function it_tries_to_acquire_a_named_lock(): void
    {
        $manager = $this->manager();

        self::assertSame('alpha', $manager->tryAcquire('alpha')?->name);
        self::assertSame(['alpha'], $manager->held());
    }

    #[Test]
    public function it_reports_ordinary_contention_as_null(): void
    {
        $manager = $this->manager(new ScriptedNamedLockGrammar(tryAcquire: ScriptedNamedLockGrammar::CONTENDED));

        self::assertNull($manager->tryAcquire('alpha'));
        self::assertSame([], $manager->held());
    }

    #[Test]
    public function it_holds_different_names_independently(): void
    {
        $manager = $this->manager();

        $manager->acquire('alpha');
        $manager->tryAcquire('beta');

        self::assertSame(['alpha', 'beta'], $manager->held());
    }

    #[Test]
    public function it_refuses_to_acquire_a_name_it_already_holds(): void
    {
        $grammar = new ScriptedNamedLockGrammar();
        $manager = $this->manager($grammar);

        $manager->acquire('alpha');

        try {
            $manager->acquire('alpha');

            self::fail('Expected a NamedLockException.');
        } catch (NamedLockException $exception) {
            self::assertSame(
                'Connection "testing" already holds the named lock "alpha"; named locks are not reentrant.',
                $exception->getMessage(),
            );
            self::assertSame('alpha', $exception->context['lock']);
            self::assertSame('acquire_lock', $exception->context['operation']);
        }

        self::assertSame([ScriptedNamedLockGrammar::GRANTED], $grammar->statements);
    }

    #[Test]
    public function it_refuses_to_try_for_a_name_it_already_holds(): void
    {
        $manager = $this->manager();

        $manager->tryAcquire('alpha');

        $this->expectException(NamedLockException::class);

        $manager->tryAcquire('alpha');
    }

    #[Test]
    public function it_releases_a_named_lock(): void
    {
        $grammar = new ScriptedNamedLockGrammar();
        $manager = $this->manager($grammar);

        $lock = $manager->acquire('alpha');
        $lock->release();

        self::assertTrue($lock->released);
        self::assertSame([], $manager->held());
        self::assertSame('alpha', $manager->acquire('alpha')->name);
        self::assertCount(3, $grammar->statements);
    }

    #[Test]
    public function it_refuses_to_release_a_lock_twice(): void
    {
        $grammar = new ScriptedNamedLockGrammar();
        $lock = $this->manager($grammar)->acquire('alpha');

        $lock->release();

        try {
            $lock->release();

            self::fail('Expected a NamedLockException.');
        } catch (NamedLockException $exception) {
            self::assertSame('The named lock "alpha" has already been released.', $exception->getMessage());
            self::assertSame(['operation' => 'release_lock', 'lock' => 'alpha'], $exception->context);
        }

        self::assertCount(2, $grammar->statements);
    }

    #[Test]
    public function it_releases_on_the_session_that_acquired_the_lock(): void
    {
        $original = $this->pdo();
        $original->exec('CREATE TABLE session_marker (id INTEGER)');
        $original->exec('INSERT INTO session_marker (id) VALUES (1)');
        $sessions = [$original, $this->pdo()];

        $manager = new PdoLockManager(
            static function () use (&$sessions): PDO {
                $session = array_shift($sessions);
                self::assertInstanceOf(PDO::class, $session);

                return $session;
            },
            new ScriptedNamedLockGrammar(release: 'SELECT id AS outcome FROM session_marker WHERE ? IS NOT NULL'),
            $this->config(),
        );

        $lock = $manager->acquire('alpha');
        $lock->release();

        self::assertTrue($lock->released);
        self::assertCount(1, $sessions);
    }

    #[Test]
    public function it_rejects_an_empty_name(): void
    {
        $this->expectException(InvalidLockNameException::class);
        $this->expectExceptionMessageIs('A named lock needs a nonempty name.');

        $this->manager()->tryAcquire('');
    }

    #[Test]
    public function it_reports_a_driver_without_named_locks(): void
    {
        $manager = new PdoLockManager($this->pdo(...), null, $this->config());

        try {
            $manager->acquire('alpha');

            self::fail('Expected an UnsupportedLockException.');
        } catch (UnsupportedLockException $exception) {
            self::assertSame('SQLite does not support named locks.', $exception->getMessage());
            self::assertSame(['database' => 'SQLite'], $exception->context);
        }

        $this->expectException(UnsupportedLockException::class);

        $manager->tryAcquire('alpha');
    }

    #[Test]
    public function it_refuses_named_locks_on_a_persistent_session(): void
    {
        $grammar = new ScriptedNamedLockGrammar();
        $session = new PDO('sqlite::memory:', options: [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_PERSISTENT => true,
        ]);
        $manager = new PdoLockManager(static fn(): PDO => $session, $grammar, $this->config());

        try {
            $manager->acquire('alpha');

            self::fail('Expected an UnsupportedLockException.');
        } catch (UnsupportedLockException $exception) {
            self::assertSame(
                'Connection "testing" uses a persistent PDO session, which outlives the connection and would keep its '
                . 'named locks; named locks need a session that ends with the connection.',
                $exception->getMessage(),
            );
            self::assertSame('acquire_lock', $exception->context['operation']);
            self::assertSame('testing', $exception->context['connection']);
        }

        try {
            $manager->tryAcquire('alpha');

            self::fail('Expected an UnsupportedLockException.');
        } catch (UnsupportedLockException) {
            self::assertSame([], $manager->held());
            self::assertSame([], $grammar->statements);
        }
    }

    #[Test]
    public function it_wraps_a_failing_acquire_statement(): void
    {
        $manager = $this->manager(new ScriptedNamedLockGrammar(acquire: 'SELECT outcome FROM missing_table WHERE ?'));

        try {
            $manager->acquire('alpha');

            self::fail('Expected a NamedLockException.');
        } catch (NamedLockException $exception) {
            self::assertSame(
                'Unable to acquire the named lock "alpha" on connection "testing" (SQLSTATE HY000).',
                $exception->getMessage(),
            );
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
            self::assertSame('HY000', $exception->context['sqlstate']);
            self::assertSame('testing', $exception->context['connection']);
        }

        self::assertSame([], $manager->held());
    }

    #[Test]
    public function it_never_turns_a_failed_try_into_contention(): void
    {
        $manager = $this->manager(new ScriptedNamedLockGrammar(tryAcquire: ScriptedNamedLockGrammar::FAILED));

        $this->expectException(NamedLockException::class);
        $this->expectExceptionMessageIs('Unable to acquire the named lock "alpha" on connection "testing".');

        $manager->tryAcquire('alpha');
    }

    #[Test]
    public function it_treats_a_missing_outcome_as_a_failure(): void
    {
        $manager = $this->manager(new ScriptedNamedLockGrammar(tryAcquire: ScriptedNamedLockGrammar::NO_ROW));

        $this->expectException(NamedLockException::class);

        $manager->tryAcquire('alpha');
    }

    #[Test]
    public function a_blocking_acquire_that_reports_contention_is_inconsistent(): void
    {
        $manager = $this->manager(new ScriptedNamedLockGrammar(acquire: ScriptedNamedLockGrammar::CONTENDED));

        $this->expectException(NamedLockException::class);
        $this->expectExceptionMessageIs('Unable to acquire the named lock "alpha" on connection "testing".');

        $manager->acquire('alpha');
    }

    #[Test]
    public function a_refused_release_keeps_the_lock_held(): void
    {
        $manager = $this->manager(new ScriptedNamedLockGrammar(release: ScriptedNamedLockGrammar::CONTENDED));
        $lock = $manager->acquire('alpha');

        try {
            $lock->release();

            self::fail('Expected a NamedLockException.');
        } catch (NamedLockException $exception) {
            self::assertSame(
                'Unable to release the named lock "alpha" on connection "testing"; the connection still counts it as '
                . 'held.',
                $exception->getMessage(),
            );
            self::assertSame('release_lock', $exception->context['operation']);
        }

        self::assertFalse($lock->released);
        self::assertSame(['alpha'], $manager->held());
    }

    #[Test]
    public function a_failed_release_still_refuses_to_acquire_the_lock_again(): void
    {
        $grammar = new ScriptedNamedLockGrammar(release: 'SELECT outcome FROM missing_table WHERE ?');
        $manager = $this->manager($grammar);
        $lock = $manager->acquire('alpha');

        try {
            $lock->release();

            self::fail('Expected a NamedLockException.');
        } catch (NamedLockException $exception) {
            self::assertInstanceOf(PDOException::class, $exception->getPrevious());
            self::assertSame('HY000', $exception->context['sqlstate']);
        }

        self::assertFalse($lock->released);

        $this->expectException(NamedLockException::class);
        $this->expectExceptionMessageIs(
            'Connection "testing" already holds the named lock "alpha"; named locks are not reentrant.',
        );

        try {
            $manager->tryAcquire('alpha');
        } finally {
            self::assertCount(2, $grammar->statements);
        }
    }

    #[Test]
    public function a_failed_release_can_be_retried(): void
    {
        $session = $this->pdo();
        $session->exec('CREATE TABLE release_outcome (outcome INTEGER)');
        $session->exec('INSERT INTO release_outcome (outcome) VALUES (0)');

        $manager = new PdoLockManager(
            static fn(): PDO => $session,
            new ScriptedNamedLockGrammar(release: 'SELECT outcome FROM release_outcome WHERE ? IS NOT NULL'),
            $this->config(),
        );
        $lock = $manager->acquire('alpha');

        try {
            $lock->release();

            self::fail('Expected a NamedLockException.');
        } catch (NamedLockException) {
            self::assertSame(['alpha'], $manager->held());
        }

        $session->exec('UPDATE release_outcome SET outcome = 1');
        $lock->release();

        self::assertTrue($lock->released);
        self::assertSame([], $manager->held());
    }

    #[Test]
    public function it_treats_a_silent_prepare_failure_as_a_failure(): void
    {
        $manager = new PdoLockManager(
            fn(): PDO => $this->pdo(PDO::ERRMODE_SILENT),
            new ScriptedNamedLockGrammar(acquire: 'SELECT outcome FROM missing_table WHERE ?'),
            $this->config(),
        );

        $this->expectException(NamedLockException::class);

        $manager->acquire('alpha');
    }

    #[Test]
    public function it_treats_a_silent_execute_failure_as_a_failure(): void
    {
        $session = $this->pdo(PDO::ERRMODE_SILENT);
        $session->exec('CREATE TABLE claims (resource TEXT UNIQUE)');

        $manager = new PdoLockManager(
            static fn(): PDO => $session,
            new ScriptedNamedLockGrammar(
                acquire: 'INSERT INTO claims (resource) VALUES (?)',
                tryAcquire: 'INSERT INTO claims (resource) VALUES (?)',
            ),
            $this->config(),
        );

        $session->exec("INSERT INTO claims (resource) VALUES ('resource:alpha')");

        $this->expectException(NamedLockException::class);

        $manager->tryAcquire('alpha');
    }

    private function manager(NamedLockGrammar $grammar = new ScriptedNamedLockGrammar()): PdoLockManager
    {
        $session = $this->pdo();

        return new PdoLockManager(static fn(): PDO => $session, $grammar, $this->config());
    }

    private function pdo(int $mode = PDO::ERRMODE_EXCEPTION): PDO
    {
        return new PDO('sqlite::memory:', options: [PDO::ATTR_ERRMODE => $mode]);
    }

    private function config(): ConnectionConfig
    {
        return new ConnectionConfig(driver: DriverName::SQLite, name: 'testing', database: ':memory:');
    }
}
