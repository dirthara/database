<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Sql;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Query\Sql\ComparisonOperator;

final class ComparisonOperatorTest extends TestCase
{
    /**
     * @return array<string, array{ComparisonOperator}>
     */
    public static function operators(): array
    {
        $operators = [];

        foreach (ComparisonOperator::cases() as $operator) {
            $operators[$operator->value] = [$operator];
        }

        return $operators;
    }

    #[Test]
    #[DataProvider('operators')]
    public function only_the_equal_operator_is_an_equality(ComparisonOperator $operator): void
    {
        self::assertSame($operator === ComparisonOperator::Equal, $operator->isEquality());
    }

    #[Test]
    #[DataProvider('operators')]
    public function only_the_not_equal_operator_is_an_inequality(ComparisonOperator $operator): void
    {
        self::assertSame($operator === ComparisonOperator::NotEqual, $operator->isInequality());
    }

    #[Test]
    public function it_recognises_equality_and_inequality(): void
    {
        self::assertTrue(ComparisonOperator::Equal->isEquality());
        self::assertFalse(ComparisonOperator::Equal->isInequality());
        self::assertTrue(ComparisonOperator::NotEqual->isInequality());
        self::assertFalse(ComparisonOperator::NotEqual->isEquality());
    }

    #[Test]
    #[DataProvider('operators')]
    public function it_parses_its_own_spelling(ComparisonOperator $operator): void
    {
        self::assertSame($operator, ComparisonOperator::parse($operator->value));
    }

    #[Test]
    #[DataProvider('operators')]
    public function it_keeps_an_operator_as_given(ComparisonOperator $operator): void
    {
        self::assertSame($operator, ComparisonOperator::parse($operator));
    }

    /**
     * @return array<string, array{string, ComparisonOperator}>
     */
    public static function spellings(): array
    {
        return [
            'lower case' => ['like', ComparisonOperator::Like],
            'mixed case' => ['Not Like', ComparisonOperator::NotLike],
            'padded' => ['  >=  ', ComparisonOperator::GreaterThanOrEqual],
            'repeated spacing' => ['not   like', ComparisonOperator::NotLike],
            'tabbed' => ["not\tlike", ComparisonOperator::NotLike],
            'standard inequality' => ['<>', ComparisonOperator::NotEqual],
        ];
    }

    #[Test]
    #[DataProvider('spellings')]
    public function it_parses_an_alternative_spelling(string $spelling, ComparisonOperator $expected): void
    {
        self::assertSame($expected, ComparisonOperator::parse($spelling));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function clauses(): array
    {
        return [
            'in' => ['IN'],
            'not in' => ['not in'],
            'is null' => ['IS NULL'],
            'is not null' => ['is not null'],
            'between' => ['BETWEEN'],
            'not between' => ['not between'],
        ];
    }

    #[Test]
    #[DataProvider('clauses')]
    public function it_rejects_a_test_that_is_not_a_comparison(string $operator): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            'The operator [%s] cannot compare two values; expected one of =, !=, >, >=, <, <=, LIKE, NOT LIKE.',
            $operator,
        ));

        ComparisonOperator::parse($operator);
    }

    #[Test]
    public function it_rejects_an_operator_it_does_not_know(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(
            'The operator [~=] cannot compare two values; expected one of =, !=, >, >=, <, <=, LIKE, NOT LIKE.',
        );

        ComparisonOperator::parse('~=');
    }

    #[Test]
    public function it_only_offers_operators_that_compare_two_values(): void
    {
        self::assertSame(
            ['=', '!=', '>', '>=', '<', '<=', 'LIKE', 'NOT LIKE'],
            array_map(static fn(ComparisonOperator $case): string => $case->value, ComparisonOperator::cases()),
        );
    }
}
