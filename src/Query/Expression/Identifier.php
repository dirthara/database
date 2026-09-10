<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Expression;

use InvalidArgumentException;

use function trim;
use function explode;
use function sprintf;
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
            throw new InvalidArgumentException('An identifier cannot be empty.');
        }

        foreach (explode('.', $this->name) as $segment) {
            if ($segment === '') {
                throw new InvalidArgumentException(sprintf('The identifier [%s] has an empty segment.', $this->name));
            }

            if (strpbrk($segment, self::FORBIDDEN) !== false) {
                throw new InvalidArgumentException(sprintf(
                    'The identifier [%s] looks like SQL rather than a name; use a raw expression instead.',
                    $this->name,
                ));
            }
        }
    }
}
