<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\ValueObjects;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\Exceptions\TransactionException;

use function str_repeat;

final class SavepointPrefixTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function accepted(): array
    {
        return [
            'lowercase' => ['dirthara'],
            'underscored' => ['my_app'],
            'leading underscore' => ['_app'],
            'digits after a letter' => ['app2'],
            'at the length limit' => [str_repeat('a', times: 24)],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rejected(): array
    {
        return [
            'empty' => [''],
            'leading digit' => ['2app'],
            'sql injection' => ['app; DROP TABLE users; --'],
            'hyphenated' => ['my-app'],
            'space' => ['my app'],
        ];
    }

    #[Test]
    public function it_defaults_to_the_package_name(): void
    {
        self::assertSame('dirthara', new SavepointPrefix()->value);
    }

    #[Test]
    #[DataProvider('accepted')]
    public function it_accepts_an_sql_identifier(string $value): void
    {
        self::assertSame($value, new SavepointPrefix($value)->value);
    }

    #[Test]
    #[DataProvider('rejected')]
    public function it_rejects_anything_else(string $value): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('must start with a letter or underscore');

        new SavepointPrefix($value);
    }

    #[Test]
    public function it_rejects_a_prefix_over_the_length_limit(): void
    {
        $this->expectException(TransactionException::class);
        $this->expectExceptionMessage('must not exceed 24 characters');

        new SavepointPrefix(str_repeat('a', times: 25));
    }

    #[Test]
    public function it_reports_the_rejected_value(): void
    {
        try {
            new SavepointPrefix('my-app');

            self::fail('Expected a TransactionException.');
        } catch (TransactionException $exception) {
            self::assertSame('my-app', $exception->getContext()['prefix']);
        }
    }
}
