<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Expression;

use InvalidArgumentException;

use function trim;
use function count;
use function sprintf;
use function preg_split;

final readonly class ExpressionFactory
{
    public static function from(string|Expression $value): Expression
    {
        if ($value instanceof Expression) {
            return $value;
        }

        $value = trim($value);
        $parts = preg_split('/\s+as\s+/i', $value) ?: [];

        if (count($parts) === 1) {
            return new Identifier($value);
        }

        if (count($parts) > 2) {
            throw new InvalidArgumentException(sprintf('The expression [%s] has more than one alias.', $value));
        }

        return new Aliased(new Identifier(trim($parts[0])), trim($parts[1]));
    }
}
