<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\ValueObjects;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Connection\ValueObjects\DsnParameter;
use Dirthara\Database\Connection\Exceptions\ConnectionException;

final class DsnParameterTest extends TestCase
{
    #[Test]
    public function it_renders_a_dsn_pair(): void
    {
        self::assertSame('TrustServerCertificate=yes', new DsnParameter('TrustServerCertificate', 'yes')->toDsn());
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidNames(): array
    {
        return [
            'a space' => ['Trust Server Certificate'],
            'a leading digit' => ['1Encrypt'],
            'an equals sign' => ['Encrypt=no'],
            'a semicolon' => ['Encrypt;Database'],
            'empty' => [''],
        ];
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_rejects_a_name_that_is_not_an_identifier(string $name): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('must be an identifier');

        new DsnParameter($name, 'yes');
    }

    #[Test]
    public function it_rejects_a_value_that_would_add_another_parameter(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage('must not contain a semicolon');

        new DsnParameter('Encrypt', 'no;Database=other');
    }
}
