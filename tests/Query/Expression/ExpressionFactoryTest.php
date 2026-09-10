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
use Dirthara\Database\Query\Expression\ExpressionFactory;

final class ExpressionFactoryTest extends TestCase
{
    #[Test]
    public function it_reads_a_name_as_an_identifier(): void
    {
        self::assertEquals(new Identifier('name'), ExpressionFactory::from('name'));
    }

    #[Test]
    public function it_reads_a_qualified_name_as_an_identifier(): void
    {
        self::assertEquals(new Identifier('users.name'), ExpressionFactory::from('users.name'));
    }

    #[Test]
    public function it_reads_a_name_that_contains_a_space(): void
    {
        self::assertEquals(new Identifier('users.first name'), ExpressionFactory::from('users.first name'));
    }

    #[Test]
    public function it_ignores_surrounding_whitespace(): void
    {
        self::assertEquals(new Identifier('name'), ExpressionFactory::from('  name  '));
        self::assertEquals(new Aliased(new Identifier('users'), 'u'), ExpressionFactory::from('  users as u  '));
    }

    #[Test]
    public function it_keeps_an_expression_as_given(): void
    {
        $identifier = new Identifier('name');
        $raw = new RawExpression('COUNT(*)');
        $aliased = new Aliased($identifier, 'n');

        self::assertSame($identifier, ExpressionFactory::from($identifier));
        self::assertSame($raw, ExpressionFactory::from($raw));
        self::assertSame($aliased, ExpressionFactory::from($aliased));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function aliases(): array
    {
        return [
            'lower case' => ['users as u', 'users', 'u'],
            'upper case' => ['users AS u', 'users', 'u'],
            'mixed case' => ['users As u', 'users', 'u'],
            'qualified name' => ['users.name as n', 'users.name', 'n'],
            'extra spacing' => ["users   as\tu", 'users', 'u'],
        ];
    }

    #[Test]
    #[DataProvider('aliases')]
    public function it_reads_an_alias(string $value, string $name, string $alias): void
    {
        self::assertEquals(new Aliased(new Identifier($name), $alias), ExpressionFactory::from($value));
    }

    #[Test]
    public function it_rejects_more_than_one_alias(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('The expression [a as b as c] has more than one alias.');

        ExpressionFactory::from('a as b as c');
    }

    #[Test]
    public function it_never_reads_a_string_as_raw_sql(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The identifier [COUNT(*)] looks like SQL rather than a name; use a raw expression instead.',
        );

        ExpressionFactory::from('COUNT(*)');
    }

    #[Test]
    public function it_rejects_an_aliased_fragment(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ExpressionFactory::from('COUNT(*) as total');
    }
}
