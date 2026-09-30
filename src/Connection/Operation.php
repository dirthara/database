<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection;

enum Operation: string
{
    case Connect = 'connect';
    case Disconnect = 'disconnect';
    case Prepare = 'prepare';
    case Execute = 'execute';
    case LastInsertId = 'last_insert_id';
    case Column = 'column';
    case Begin = 'begin';
    case Commit = 'commit';
    case Rollback = 'rollback';
    case Savepoint = 'savepoint';
    case ReleaseSavepoint = 'release_savepoint';
    case RollbackToSavepoint = 'rollback_to_savepoint';
    case AcquireLock = 'acquire_lock';
    case ReleaseLock = 'release_lock';

    public function describe(): string
    {
        return match ($this) {
            self::Connect => 'connect',
            self::Disconnect => 'disconnect',
            self::Prepare => 'prepare a query',
            self::Execute => 'execute a query',
            self::LastInsertId => 'read the last inserted id',
            self::Column => 'select a column',
            self::Begin => 'begin a transaction',
            self::Commit => 'commit the transaction',
            self::Rollback => 'roll back the transaction',
            self::Savepoint => 'create a savepoint',
            self::ReleaseSavepoint => 'release a savepoint',
            self::RollbackToSavepoint => 'roll back to a savepoint',
            self::AcquireLock => 'acquire a named lock',
            self::ReleaseLock => 'release a named lock',
        };
    }
}
