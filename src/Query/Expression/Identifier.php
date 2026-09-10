<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Expression;

use function is_string;

final readonly class Identifier implements Expression
{
    public function __construct(
        public string $name,
    ) {}

    public static function wrap(string|Expression $value): Expression
    {
        return is_string($value) ? new self($value) : $value;
    }
}
