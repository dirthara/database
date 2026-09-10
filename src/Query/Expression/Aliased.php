<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Expression;

use InvalidArgumentException;

use function trim;

final readonly class Aliased implements Expression
{
    public function __construct(
        public Expression $expression,
        public string $alias,
    ) {
        if (trim($this->alias) === '') {
            throw new InvalidArgumentException('An alias cannot be empty.');
        }
    }
}
