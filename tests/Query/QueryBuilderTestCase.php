<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query;

use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Query\Clause\WhereClause;
use Dirthara\Database\Tests\ConnectionTestCase;
use Dirthara\Database\Query\Expression\Expression;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Queries\CompiledQuery;
use Dirthara\Database\Query\Grammar\SQLiteQueryGrammar;
use Dirthara\Database\Tests\Query\Doubles\RecordingGrammar;

use function sprintf;

abstract class QueryBuilderTestCase extends ConnectionTestCase
{
    protected RecordingGrammar $grammar;

    protected function setUp(): void
    {
        parent::setUp();

        $this->grammar = new RecordingGrammar();
    }

    /**
     * A builder over a connection that is never reached, for asserting on builder state.
     */
    protected function builder(string|Expression $table = 'users'): QueryBuilder
    {
        return new QueryBuilder($this->sqlite(), $this->grammar, $table);
    }

    /**
     * A builder whose grammar returns the given SQL, over a seeded `users` table.
     *
     * @param list<int|string> $bindings
     */
    protected function executing(string $sql, array $bindings = []): QueryBuilder
    {
        $this->grammar->result = new CompiledQuery($sql, $bindings);

        return new QueryBuilder($this->seeded(), $this->grammar, 'users');
    }

    /**
     * A builder over a real grammar and a seeded table, for behaviour that depends on paging.
     */
    protected function paging(string ...$extra): QueryBuilder
    {
        $connection = $this->seeded();

        foreach ($extra as $name) {
            $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', [$name, 1]);
        }

        return new QueryBuilder($connection, new SQLiteQueryGrammar(), 'users');
    }

    protected static function identifier(Expression $expression): Identifier
    {
        if (!$expression instanceof Identifier) {
            self::fail(sprintf('Expected an identifier, got %s.', $expression::class));
        }

        return $expression;
    }

    /**
     * @template TClause of WhereClause
     *
     * @param class-string<TClause> $expected
     *
     * @return TClause
     */
    protected static function clause(string $expected, WhereClause $actual): WhereClause
    {
        if (!$actual instanceof $expected) {
            self::fail(sprintf('Expected a %s clause, got %s.', $expected, $actual::class));
        }

        return $actual;
    }

    protected function seeded(): Connection
    {
        $connection = $this->withUsers();

        $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);
        $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Grace', 0]);

        return $connection;
    }
}
