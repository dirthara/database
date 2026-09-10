<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Grammar;

use ArrayIterator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Query\Grammar\MySqlQueryGrammar;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Database\Query\Grammar\QueryGrammarResolver;
use Dirthara\Database\Query\Grammar\SqlServerQueryGrammar;
use Dirthara\Database\Query\Grammar\PostgresSqlQueryGrammar;

final class QueryGrammarResolverTest extends TestCase
{
    #[Test]
    public function it_resolves_a_grammar_registered_for_a_driver(): void
    {
        $grammar = new SQLiteQueryGrammar();

        $resolver = new QueryGrammarResolver();
        $resolver->register(DriverName::SQLite, $grammar);

        self::assertSame($grammar, $resolver->resolve(DriverName::SQLite));
    }

    #[Test]
    public function it_registers_a_grammar_for_every_driver(): void
    {
        $mysql = new MySqlQueryGrammar();
        $postgres = new PostgresSqlQueryGrammar();
        $sqlServer = new SqlServerQueryGrammar();
        $sqlite = new SQLiteQueryGrammar();

        $resolver = new QueryGrammarResolver();
        $resolver->register(DriverName::MySql, $mysql);
        $resolver->register(DriverName::PostgresSql, $postgres);
        $resolver->register(DriverName::SqlServer, $sqlServer);
        $resolver->register(DriverName::SQLite, $sqlite);

        self::assertSame($mysql, $resolver->resolve(DriverName::MySql));
        self::assertSame($postgres, $resolver->resolve(DriverName::PostgresSql));
        self::assertSame($sqlServer, $resolver->resolve(DriverName::SqlServer));
        self::assertSame($sqlite, $resolver->resolve(DriverName::SQLite));
    }

    #[Test]
    public function it_registers_grammars_given_to_the_constructor(): void
    {
        $mysql = new MySqlQueryGrammar();
        $sqlite = new SQLiteQueryGrammar();

        $resolver = new QueryGrammarResolver([
            DriverName::MySql->value => $mysql,
            DriverName::SQLite->value => $sqlite,
        ]);

        self::assertSame($mysql, $resolver->resolve(DriverName::MySql));
        self::assertSame($sqlite, $resolver->resolve(DriverName::SQLite));
    }

    #[Test]
    public function it_registers_grammars_from_any_iterable(): void
    {
        $grammar = new SQLiteQueryGrammar();

        $resolver = new QueryGrammarResolver(new ArrayIterator([DriverName::SQLite->value => $grammar]));

        self::assertSame($grammar, $resolver->resolve(DriverName::SQLite));
    }

    #[Test]
    public function it_resolves_a_driver_registered_under_its_name(): void
    {
        $grammar = new SQLiteQueryGrammar();

        $resolver = new QueryGrammarResolver(['sqlite' => $grammar]);

        self::assertSame($grammar, $resolver->resolve(DriverName::SQLite));
    }

    #[Test]
    public function it_resolves_a_driver_given_as_a_name(): void
    {
        $grammar = new SQLiteQueryGrammar();

        $resolver = new QueryGrammarResolver();
        $resolver->register(DriverName::SQLite, $grammar);

        self::assertSame($grammar, $resolver->resolve('sqlite'));
    }

    #[Test]
    public function it_rejects_a_driver_registered_twice(): void
    {
        $resolver = new QueryGrammarResolver([DriverName::SQLite->value => new SQLiteQueryGrammar()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A query grammar is already registered for driver [sqlite].');

        $resolver->register(DriverName::SQLite, new SQLiteQueryGrammar());
    }

    #[Test]
    public function it_rejects_a_driver_registered_twice_under_a_name_and_an_enum(): void
    {
        $resolver = new QueryGrammarResolver();
        $resolver->register('sqlite', new SQLiteQueryGrammar());

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A query grammar is already registered for driver [sqlite].');

        $resolver->register(DriverName::SQLite, new SQLiteQueryGrammar());
    }

    /**
     * @return iterable<string, SQLiteQueryGrammar>
     */
    public static function duplicated(): iterable
    {
        yield 'sqlite' => new SQLiteQueryGrammar();

        yield 'sqlite' => new SQLiteQueryGrammar();
    }

    #[Test]
    public function it_rejects_a_driver_the_constructor_is_given_twice(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A query grammar is already registered for driver [sqlite].');

        new QueryGrammarResolver(self::duplicated());
    }

    #[Test]
    public function it_registers_nothing_by_default(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new QueryGrammarResolver()->resolve(DriverName::SQLite);
    }

    #[Test]
    public function it_rejects_a_driver_without_a_grammar(): void
    {
        $resolver = new QueryGrammarResolver([DriverName::SQLite->value => new SQLiteQueryGrammar()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No query grammar has been registered for driver [mysql].');

        $resolver->resolve(DriverName::MySql);
    }

    #[Test]
    public function it_rejects_an_unregistered_driver_given_as_a_name(): void
    {
        $resolver = new QueryGrammarResolver([DriverName::SQLite->value => new SQLiteQueryGrammar()]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No query grammar has been registered for driver [mysql].');

        $resolver->resolve('mysql');
    }
}
