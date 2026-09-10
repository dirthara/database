<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Query\Operator;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\DataProvider;
use Dirthara\Database\Query\Operator\ComparisonOperator;

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
}
