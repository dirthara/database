<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

use Dirthara\Database\Connection\Driver\DriverName;
use Dirthara\Database\Exception\GrammarRegistryException;

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
     * @throws GrammarRegistryException
     */
    public function __construct(iterable $grammars = [])
    {
        foreach ($grammars as $driver => $grammar) {
            $this->register($driver, $grammar);
        }
    }

    /**
     * @throws GrammarRegistryException
     */
    public function register(string|DriverName $driver, QueryGrammar $grammar): void
    {
        $name = $this->name($driver);

        if (array_key_exists($name, $this->grammars)) {
            throw GrammarRegistryException::alreadyRegistered($name);
        }

        $this->grammars[$name] = $grammar;
    }

    /**
     * @throws GrammarRegistryException
     */
    public function resolve(string|DriverName $driver): QueryGrammar
    {
        $name = $this->name($driver);

        return $this->grammars[$name] ?? throw GrammarRegistryException::notRegistered($name);
    }

    private function name(string|DriverName $driver): string
    {
        return $driver instanceof DriverName ? $driver->value : $driver;
    }
}
