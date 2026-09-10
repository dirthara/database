<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Expression;

final readonly class Expression
{
    public function __construct(
        public string $expression,
    ) {}
}
