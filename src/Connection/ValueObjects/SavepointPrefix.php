<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\ValueObjects;

use Dirthara\Database\Connection\Exceptions\TransactionException;

use function strlen;
use function sprintf;
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
            throw new TransactionException('The savepoint prefix must start with a letter or underscore and contain only letters, digits, and underscores.', context: [
                'prefix' => $value,
            ]);
        }

        if (strlen($value) > self::MAX_LENGTH) {
            throw new TransactionException(
                sprintf('The savepoint prefix must not exceed %d characters.', self::MAX_LENGTH),
                context: ['prefix' => $value],
            );
        }
    }
}
