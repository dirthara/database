<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Expression;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Query\Expression\Aliased;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Expression\RawExpression;

final class IdentifierTest extends TestCase
{
    #[Test]
    public function it_carries_the_name_it_was_given(): void
    {
        self::assertSame('users.id', new Identifier('users.id')->name);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function accepted(): array
    {
        return [
            'name' => ['name'],
            'qualified name' => ['users.name'],
            'wildcard' => ['*'],
            'qualified wildcard' => ['users.*'],
            'schema qualified' => ['app.users.name'],
            'quoted keyword' => ['order'],
            'name with a space' => ['first name'],
            'name with a quote' => ['we`ird'],
        ];
    }

    #[Test]
    #[DataProvider('accepted')]
    public function it_accepts_a_name(string $name): void
    {
        self::assertSame($name, new Identifier($name)->name);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function rejected(): array
    {
        return [
            'empty' => ['', 'An identifier cannot be empty.'],
            'blank' => ['   ', 'An identifier cannot be empty.'],
            'trailing dot' => ['users.', 'The identifier [users.] has an empty segment.'],
            'leading dot' => ['.users', 'The identifier [.users] has an empty segment.'],
            'double dot' => ['a..b', 'The identifier [a..b] has an empty segment.'],
            'function call' => [
                'COUNT(*)',
                'The identifier [COUNT(*)] looks like SQL rather than a name; use a raw expression instead.',
            ],
            'argument list' => [
                'IFNULL(a, b)',
                'The identifier [IFNULL(a, b)] looks like SQL rather than a name; use a raw expression instead.',
            ],
        ];
    }

    #[Test]
    #[DataProvider('rejected')]
    public function it_rejects_anything_that_is_not_a_name(string $name, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        new Identifier($name);
    }

    #[Test]
    public function an_alias_carries_its_expression_and_name(): void
    {
        $identifier = new Identifier('users');
        $aliased = new Aliased($identifier, 'u');

        self::assertSame($identifier, $aliased->expression);
        self::assertSame('u', $aliased->alias);
    }

    #[Test]
    public function an_alias_can_name_a_raw_expression(): void
    {
        $raw = new RawExpression('COUNT(*)');

        self::assertSame($raw, new Aliased($raw, 'total')->expression);
    }

    #[Test]
    public function an_alias_cannot_be_empty(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('An alias cannot be empty.');

        new Aliased(new Identifier('users'), '  ');
    }

    #[Test]
    public function a_raw_expression_carries_its_sql_and_bindings(): void
    {
        $raw = new RawExpression('views > ?', [10]);

        self::assertSame('views > ?', $raw->sql);
        self::assertSame([10], $raw->bindings);
    }

    #[Test]
    public function a_raw_expression_has_no_bindings_by_default(): void
    {
        self::assertSame([], new RawExpression('COUNT(*)')->bindings);
    }
}
