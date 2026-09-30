<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Operation;

use function array_map;

final class OperationTest extends TestCase
{
    #[Test]
    public function every_operation_reads_after_unable_to(): void
    {
        self::assertSame(
            [
                'connect',
                'disconnect',
                'prepare a query',
                'execute a query',
                'read the last inserted id',
                'select a column',
                'begin a transaction',
                'commit the transaction',
                'roll back the transaction',
                'create a savepoint',
                'release a savepoint',
                'roll back to a savepoint',
                'acquire a named lock',
                'release a named lock',
            ],
            array_map(static fn(Operation $operation): string => $operation->describe(), Operation::cases()),
        );
    }
}
