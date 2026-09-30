<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Pdo;

use PDOException;

use function is_array;
use function is_string;
use function array_key_exists;

final class PdoError
{
    /**
     * @return array<string, mixed>
     */
    public static function describe(PDOException $cause): array
    {
        $context = [];

        $sqlstate = self::sqlstate($cause);

        if ($sqlstate !== null) {
            $context['sqlstate'] = $sqlstate;
        }

        $errorInfo = $cause->errorInfo;

        if (is_array($errorInfo) && array_key_exists(1, $errorInfo)) {
            $context['driver_code'] = $errorInfo[1];
        }

        return $context;
    }

    public static function sqlstate(PDOException $cause): ?string
    {
        $sqlstate = $cause->getCode();

        return is_string($sqlstate) && $sqlstate !== '' ? $sqlstate : null;
    }

    public static function suffix(PDOException $cause): string
    {
        $sqlstate = self::sqlstate($cause);

        return $sqlstate === null ? '' : ' (SQLSTATE ' . $sqlstate . ')';
    }
}
