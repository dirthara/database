<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\ValueObjects;

use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Exception\InvalidConnectionConfigException;

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
            throw InvalidConnectionConfigException::semicolonInDsnValue($field);
        }
    }
}
