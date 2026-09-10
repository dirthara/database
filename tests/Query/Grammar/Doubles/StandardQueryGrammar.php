<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Grammar\Doubles;

use Dirthara\Database\Query\Grammar\SqlQueryGrammar;

/**
 * The shared grammar with nothing overridden, so its standard SQL defaults can be tested.
 */
final class StandardQueryGrammar extends SqlQueryGrammar
{
    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '"');
    }
}
