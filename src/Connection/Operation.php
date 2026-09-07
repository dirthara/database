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
}
