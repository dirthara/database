<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Expression;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Query\Expression\Identifier;
use Dirthara\Database\Query\Expression\RawExpression;

final class IdentifierTest extends TestCase
{
    #[Test]
    public function it_carries_the_name_it_was_given(): void
    {
        self::assertSame('users.id', new Identifier('users.id')->name);
    }

    #[Test]
    public function it_wraps_a_name_in_an_identifier(): void
    {
        $identifier = Identifier::wrap('name');

        self::assertInstanceOf(Identifier::class, $identifier);
        self::assertSame('name', $identifier->name);
    }

    #[Test]
    public function it_keeps_an_identifier_as_given(): void
    {
        $identifier = new Identifier('name');

        self::assertSame($identifier, Identifier::wrap($identifier));
    }

    #[Test]
    public function it_keeps_a_raw_expression_as_given(): void
    {
        $raw = new RawExpression('COUNT(*)');

        self::assertSame($raw, Identifier::wrap($raw));
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
