<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Fixtures\Query;

use Dirthara\Database\Query\Grammar\SqlQueryGrammar;

final class LocklessGrammar extends SqlQueryGrammar
{
    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '"');
    }
}
