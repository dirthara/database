<?php

declare(strict_types=1);

namespace Dirthara\Database\Tests\Connection\Transaction;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use Dirthara\Database\Connection\ValueObjects\SavepointPrefix;
use Dirthara\Database\Connection\Transaction\StandardTransactionGrammar;
use Dirthara\Database\Connection\Transaction\SqlServerTransactionGrammar;

final class TransactionGrammarTest extends TestCase
{
    #[Test]
    public function the_standard_grammar_uses_the_sql_standard_statements(): void
    {
        $grammar = new StandardTransactionGrammar(new SavepointPrefix());

        self::assertSame('dirthara1', $grammar->savepointName(1));
        self::assertSame('SAVEPOINT dirthara1', $grammar->createSavepoint('dirthara1'));
        self::assertSame('RELEASE SAVEPOINT dirthara1', $grammar->releaseSavepoint('dirthara1'));
        self::assertSame('ROLLBACK TO SAVEPOINT dirthara1', $grammar->rollbackToSavepoint('dirthara1'));
    }

    #[Test]
    public function the_sql_server_grammar_saves_a_named_transaction_and_cannot_release_it(): void
    {
        $grammar = new SqlServerTransactionGrammar(new SavepointPrefix());

        self::assertSame('SAVE TRANSACTION dirthara2', $grammar->createSavepoint('dirthara2'));
        self::assertNull($grammar->releaseSavepoint('dirthara2'));
        self::assertSame('ROLLBACK TRANSACTION dirthara2', $grammar->rollbackToSavepoint('dirthara2'));
    }

    #[Test]
    public function it_names_savepoints_with_the_given_prefix(): void
    {
        $grammar = new StandardTransactionGrammar(new SavepointPrefix('my_app'));

        self::assertSame('my_app3', $grammar->savepointName(3));
    }
}
