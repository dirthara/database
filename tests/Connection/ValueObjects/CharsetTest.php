<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\ValueObjects;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Connection\ValueObjects\Charset;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

final class CharsetTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function accepted(): array
    {
        return [
            'utf8mb4' => ['utf8mb4'],
            'latin1' => ['latin1'],
            'underscored' => ['utf8_general'],
            'hyphenated' => ['UTF-8'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejected(): array
    {
        return [
            'empty' => [''],
            'quoted injection' => ["utf8'; DROP TABLE users; --"],
            'space' => ['utf 8'],
            'semicolon' => ['utf8;charset=other'],
            'newline' => ["utf8\nSET x"],
        ];
    }

    #[Test]
    #[DataProvider('accepted')]
    public function it_accepts_an_identifier(string $value): void
    {
        self::assertSame($value, new Charset($value)->value);
    }

    #[Test]
    #[DataProvider('rejected')]
    public function it_rejects_anything_else(string $value): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('The configured charset is not a valid identifier.');

        new Charset($value);
    }

    #[Test]
    public function it_reports_the_rejected_value(): void
    {
        try {
            new Charset('utf 8');

            self::fail('Expected a ConnectionException.');
        } catch (ConnectionException $exception) {
            self::assertSame('utf 8', $exception->getContext()['charset']);
        }
    }
}
