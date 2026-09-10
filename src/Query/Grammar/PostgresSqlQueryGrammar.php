<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Grammar;

final class PostgresSqlQueryGrammar extends SqlQueryGrammar
{
    protected function quote(string $identifier): string
    {
        return $this->escape($identifier, '"');
    }
}
