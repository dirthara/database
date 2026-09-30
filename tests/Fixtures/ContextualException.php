<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Fixtures;

use RuntimeException;
use Dirthara\Database\Exception\DatabaseException;
use Dirthara\Database\Exception\HasExceptionContext;

final class ContextualException extends RuntimeException implements DatabaseException
{
    use HasExceptionContext;

    public static function describe(string $value): string
    {
        return self::printable($value);
    }
}
