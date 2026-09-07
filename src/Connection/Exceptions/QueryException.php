<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Exceptions;

use PDOException;
use Dirthara\Database\Exceptions\DatabaseException;

use function is_int;
use function is_string;

class QueryException extends DatabaseException
{
    public static function fromPdo(PDOException $exception, string $query): self
    {
        $code = $exception->getCode();
        $context = ['query' => $query];

        if (is_string($code)) {
            $context['sqlstate'] = $code;
        }

        return new self(
            message: $exception->getMessage(),
            code: is_int($code) ? $code : 0,
            previous: $exception,
            context: $context,
        );
    }
}
