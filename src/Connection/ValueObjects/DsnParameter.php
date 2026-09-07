<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\ValueObjects;

use Dirthara\Database\Connection\Exceptions\ConnectionException;

use function preg_match;

/**
 * One driver-specific `Key=Value` pair appended to a DSN.
 *
 * A DSN is assembled by concatenation, so both halves are checked here: the name
 * against an identifier pattern, and the value through DsnValue, which refuses a
 * semicolon. Neither half can add a field of its own.
 */
final readonly class DsnParameter
{
    private const string PATTERN = '/^[A-Za-z][A-Za-z0-9_]*$/';

    public DsnValue $parameter;

    /**
     * @throws ConnectionException
     */
    public function __construct(
        public string $name,
        string $value,
    ) {
        if (preg_match(self::PATTERN, $name) !== 1) {
            throw new ConnectionException('The DSN parameter name must be an identifier.', context: [
                'parameter' => $name,
            ]);
        }

        $this->parameter = new DsnValue($name, $value);
    }

    public function toDsn(): string
    {
        return $this->name . '=' . $this->parameter->value;
    }
}
