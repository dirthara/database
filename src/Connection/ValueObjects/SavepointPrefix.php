<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\ValueObjects;

use Dirthara\Database\Exception\TransactionException;
use Dirthara\Database\Exception\InvalidConnectionConfigException;

use function strlen;
use function preg_match;

final readonly class SavepointPrefix
{
    private const string PATTERN = '/^[A-Za-z_][A-Za-z0-9_]*$/';

    private const int MAX_LENGTH = 24;

    /**
     * @throws TransactionException
     */
    public function __construct(
        public string $value = 'dirthara',
    ) {
        if (preg_match(self::PATTERN, $value) !== 1) {
            throw InvalidConnectionConfigException::invalidSavepointPrefix($value);
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw InvalidConnectionConfigException::savepointPrefixTooLong($value, self::MAX_LENGTH);
        }
    }
}
