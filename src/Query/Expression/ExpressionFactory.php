<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Expression;

use InvalidArgumentException;

use function trim;
use function sprintf;
use function preg_match;
use function preg_match_all;

final readonly class ExpressionFactory
{
    public static function from(string|Expression $value): Expression
    {
        if ($value instanceof Expression) {
            return $value;
        }

        $value = trim($value);
        $matches = [];

        if ((int) preg_match_all('/\s+as\s+/i', $value) > 1) {
            throw new InvalidArgumentException(sprintf('The expression [%s] has more than one alias.', $value));
        }

        if (preg_match('/^(.+?)\s+as\s+(.+)$/i', $value, $matches) === 1) {
            return new Aliased(new Identifier(trim($matches[1])), trim($matches[2]));
        }

        return new Identifier($value);
    }
}
