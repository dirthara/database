<?php

declare(strict_types=1);

namespace Dirthara\Database\Query\Sql;

use InvalidArgumentException;

use function trim;
use function implode;
use function sprintf;
use function array_map;
use function strtoupper;
use function preg_replace;

enum ComparisonOperator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Like = 'LIKE';
    case NotLike = 'NOT LIKE';

    /**
     * @var array<string, string>
     */
    private const array ALIASES = ['<>' => '!='];

    /**
     * @throws InvalidArgumentException
     */
    public static function parse(string|self $operator): self
    {
        if ($operator instanceof self) {
            return $operator;
        }

        $collapsed = preg_replace('/\s+/', replacement: ' ', subject: trim($operator)) ?? $operator;
        $name = strtoupper($collapsed);
        $name = self::ALIASES[$name] ?? $name;

        return (
            self::tryFrom($name) ?? throw new InvalidArgumentException(sprintf(
                'The operator [%s] cannot compare two values; expected one of %s.',
                $operator,
                implode(', ', array_map(static fn(self $case): string => $case->value, self::cases())),
            ))
        );
    }

    public function isEquality(): bool
    {
        return $this === self::Equal;
    }

    public function isInequality(): bool
    {
        return $this === self::NotEqual;
    }
}
