<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\ValueObjects;

use Dirthara\Database\Exception\ConnectionException;
use Dirthara\Database\Exception\InvalidConnectionConfigException;

use function preg_match;

final readonly class Charset
{
    private const string PATTERN = '/^[A-Za-z0-9_-]+$/';

    /**
     * @throws ConnectionException
     */
    public function __construct(
        public string $value,
    ) {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidConnectionConfigException::invalidCharset($value);
        }
    }
}
