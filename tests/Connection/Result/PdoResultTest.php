<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\Result;

use PDOStatement;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\Connection;
use Dirthara\Database\Tests\ConnectionTestCase;
use Dirthara\Database\Connection\Result\PdoResult;
use Dirthara\Database\Connection\Exceptions\ResultException;

final class PdoResultTest extends ConnectionTestCase
{
    private function seeded(): Connection
    {
        $connection = $this->withUsers();

        $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Ada', 1]);
        $connection->execute('INSERT INTO users (name, active) VALUES (?, ?)', ['Grace', 0]);

        return $connection;
    }

    #[Test]
    public function it_returns_the_first_row(): void
    {
        self::assertSame(['name' => 'Ada'], $this->seeded()->execute('SELECT name FROM users ORDER BY id')->first());
    }

    #[Test]
    public function it_returns_null_for_an_empty_result(): void
    {
        self::assertNull($this->withUsers()->execute('SELECT name FROM users')->first());
    }

    #[Test]
    public function it_returns_every_row(): void
    {
        self::assertSame(
            [['name' => 'Ada'], ['name' => 'Grace']],
            $this->seeded()->execute('SELECT name FROM users ORDER BY id')->all(),
        );
    }

    #[Test]
    public function it_selects_a_column_by_position(): void
    {
        self::assertSame(
            ['Ada', 'Grace'],
            $this->seeded()->execute('SELECT name, active FROM users ORDER BY id')->column(),
        );
    }

    #[Test]
    public function it_selects_a_column_by_name(): void
    {
        self::assertSame(
            [1, 0],
            $this->seeded()->execute('SELECT name, active FROM users ORDER BY id')->column('active'),
        );
    }

    #[Test]
    public function it_rejects_an_unknown_column_name(): void
    {
        try {
            $this->seeded()->execute('SELECT name FROM users')->column('missing');

            self::fail('Expected a ResultException.');
        } catch (ResultException $exception) {
            self::assertSame('The result set has no column "missing".', $exception->getMessage());
            self::assertSame(['name'], $exception->getContext()['columns']);
        }
    }

    #[Test]
    public function it_rejects_an_unknown_column_name_on_an_empty_result(): void
    {
        $this->expectException(ResultException::class);
        $this->expectExceptionMessage('The result set has no column "missing".');

        $this->withUsers()->execute('SELECT name FROM users')->column('missing');
    }

    #[Test]
    public function it_rejects_a_column_position_out_of_range(): void
    {
        $this->expectException(ResultException::class);
        $this->expectExceptionMessage('The result set has no column #4.');

        $this->seeded()->execute('SELECT name FROM users')->column(4);
    }

    #[Test]
    public function it_rejects_a_negative_column_position(): void
    {
        $this->expectException(ResultException::class);

        $this->seeded()->execute('SELECT name FROM users')->column(-1);
    }

    #[Test]
    public function it_reports_the_affected_rows_of_a_write(): void
    {
        $connection = $this->seeded();

        self::assertSame(2, $connection->execute('UPDATE users SET active = ?', [1])->affectedRows());
    }

    #[Test]
    public function it_iterates_the_rows(): void
    {
        $names = [];

        foreach ($this->seeded()->execute('SELECT name FROM users ORDER BY id')->iterate() as $row) {
            $names[] = $row['name'];
        }

        self::assertSame(['Ada', 'Grace'], $names);
    }

    #[Test]
    public function it_rejects_a_column_name_when_the_driver_exposes_no_metadata(): void
    {
        $statement = $this->createStub(PDOStatement::class);
        $statement->method('columnCount')->willReturn(1);
        $statement->method('getColumnMeta')->willReturn(false);

        try {
            new PdoResult($statement)->column('name');

            self::fail('Expected a ResultException.');
        } catch (ResultException $exception) {
            self::assertStringContainsString('does not expose column metadata', $exception->getMessage());
            self::assertSame('name', $exception->getContext()['column']);
            self::assertSame('column', $exception->getContext()['operation']);
        }
    }
}
