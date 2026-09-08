<?php

declare(strict_types=1);


namespace Dirthara\Database;

use Dirthara\Database\Query\QueryBuilder;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Connection\ConnectionManager;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

final readonly class Database
{
    public function __construct(
        private ConnectionManager $connections,
    ) {}

    /**
     * @throws ConnectionException
     */
    public function connection(?string $name = null): Connection
    {
        return $this->connections->connection($name);
    }

    public function table(string $table, ?string $connection = null): QueryBuilder
    {
        // @todo
    }
}
