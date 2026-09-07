<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\ValueObjects;

use Dirthara\Database\Connection\Exceptions\ConnectionException;

use function sprintf;
use function str_contains;

final readonly class DsnValue
{
    /**
     * @throws ConnectionException
     */
    public function __construct(
        public string $field,
        public string $value,
    ) {
        if (str_contains($value, ';')) {
            throw new ConnectionException(sprintf('The configured %s must not contain a semicolon.', $field), context: [
                'field' => $field,
                'value' => $value,
            ]);
        }
    }
}
