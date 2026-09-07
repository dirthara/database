<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\ValueObjects;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Connection\ValueObjects\DsnValue;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

final class DsnValueTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function accepted(): array
    {
        return [
            'hostname' => ['db.example.com'],
            'ipv4' => ['127.0.0.1'],
            'socket path' => ['/var/run/mysqld/mysqld.sock'],
            'database name' => ['app_production'],
            'equals sign' => ['weird=name'],
        ];
    }

    #[Test]
    #[DataProvider('accepted')]
    public function it_accepts_a_value_without_a_semicolon(string $value): void
    {
        self::assertSame($value, new DsnValue('host', $value)->value);
    }

    #[Test]
    public function it_rejects_a_value_that_would_append_dsn_parameters(): void
    {
        try {
            new DsnValue('host', 'localhost;dbname=other');

            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $exception) {
            self::assertSame('The configured host must not contain a semicolon.', $exception->getMessage());
            self::assertSame('host', $exception->getContext()['field']);
        }
    }

    #[Test]
    public function it_names_the_field_it_rejected(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('The configured database must not contain a semicolon.');

        new DsnValue('database', 'app;Trusted_Connection=yes');
    }

    #[Test]
    public function it_keeps_the_field_name(): void
    {
        self::assertSame('database', new DsnValue('database', 'app')->field);
    }
}
