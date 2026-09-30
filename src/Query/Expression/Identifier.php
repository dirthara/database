<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Expression;

use Dirthara\Database\Exception\InvalidExpressionException;

use function trim;
use function explode;
use function strpbrk;

final readonly class Identifier implements Expression
{
    /**
     * The characters that never appear in a name a caller meant to be quoted.
     */
    private const string FORBIDDEN = '(),';

    public function __construct(
        public string $name,
    ) {
        if (trim($this->name) === '') {
            throw InvalidExpressionException::emptyIdentifier();
        }

        foreach (explode('.', $this->name) as $segment) {
            if ($segment === '') {
                throw InvalidExpressionException::emptySegment($this->name);
            }

            if (strpbrk($segment, self::FORBIDDEN) !== false) {
                throw InvalidExpressionException::sqlInIdentifier($this->name);
            }
        }
    }
}
