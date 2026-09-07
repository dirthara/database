<?php

namespace Dirthara\Database\Connection\Transaction;

interface TransactionGrammar
{
    public function createSavepoint(string $name): string;

    public function releaseSavepoint(string $name): ?string;

    public function rollbackToSavepoint(string $name): string;
}