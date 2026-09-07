<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Exceptions;

use PDOException;
use Dirthara\Database\Exceptions\DatabaseException;

class QueryException extends DatabaseException
{
    public static function fromPdo(PDOException $exception, string $query): self
    {
        return new self(message: $exception->getMessage(), code: $exception->getCode(), previous: $exception, context: [
            'query' => $query,
        ]);
    }
}
