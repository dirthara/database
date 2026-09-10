<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use InvalidArgumentException;
use Dirthara\Database\Connection\Driver\DriverName;

use function sprintf;
use function array_key_exists;

final class QueryGrammarResolver
{
    /**
     * @var array<string, QueryGrammar>
     */
    private array $grammars = [];

    /**
     * @param iterable<string, QueryGrammar> $grammars
     *
     * @throws InvalidArgumentException
     */
    public function __construct(iterable $grammars = [])
    {
        foreach ($grammars as $driver => $grammar) {
            $this->register($driver, $grammar);
        }
    }

    /**
     * @throws InvalidArgumentException
     */
    public function register(string|DriverName $driver, QueryGrammar $grammar): void
    {
        $name = $this->name($driver);

        if (array_key_exists($name, $this->grammars)) {
            throw new InvalidArgumentException(sprintf(
                'A query grammar is already registered for driver [%s].',
                $name,
            ));
        }

        $this->grammars[$name] = $grammar;
    }

    /**
     * @throws InvalidArgumentException
     */
    public function resolve(string|DriverName $driver): QueryGrammar
    {
        $name = $this->name($driver);

        return (
            $this->grammars[$name] ?? throw new InvalidArgumentException(sprintf(
                'No query grammar has been registered for driver [%s].',
                $name,
            ))
        );
    }

    private function name(string|DriverName $driver): string
    {
        return $driver instanceof DriverName ? $driver->value : $driver;
    }
}
