<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Pdo;

use PDOException;

use function is_int;
use function is_array;
use function is_string;
use function array_key_exists;

final class PdoError
{
    /**
     * @return array<string, mixed>
     */
    public static function describe(?PDOException $cause): array
    {
        if ($cause === null) {
            return [];
        }

        $context = [];

        $sqlstate = $cause->getCode();

        if (is_string($sqlstate) && $sqlstate !== '') {
            $context['sqlstate'] = $sqlstate;
        }

        $errorInfo = $cause->errorInfo;

        if (is_array($errorInfo) && array_key_exists(1, $errorInfo)) {
            $context['driver_code'] = $errorInfo[1];
        }

        return $context;
    }

    public static function code(PDOException $cause): int
    {
        $code = $cause->getCode();

        return is_int($code) ? $code : 0;
    }
}
