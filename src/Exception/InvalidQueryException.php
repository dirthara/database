<?php

declare(strict_types=1);

namespace Dirthara\Database\Exception;

use InvalidArgumentException;

use function sprintf;
use function ucfirst;

final class InvalidQueryException extends InvalidArgumentException implements DatabaseException
{
    use HasExceptionContext;

    /**
     * @param array<string, mixed> $context
     */
    private function __construct(string $message, array $context = [])
    {
        parent::__construct($message);

        $this->context = $context;
    }

    public static function emptyTable(): self
    {
        return new self(message: 'A query table cannot be empty.');
    }

    public static function negativeLimit(int $limit): self
    {
        return new self(message: 'A query limit cannot be negative.', context: ['limit' => $limit]);
    }

    public static function negativeOffset(int $offset): self
    {
        return new self(message: 'A query offset cannot be negative.', context: ['offset' => $offset]);
    }

    public static function orderedUnionOperand(): self
    {
        return new self(message: 'A union operand cannot order or page itself; order and page the union instead.');
    }

    public static function invalidChunkSize(int $size): self
    {
        return new self(message: 'A chunk size must be at least one row.', context: ['size' => $size]);
    }

    public static function unorderedChunk(): self
    {
        return new self(message: 'A chunked query needs an ordering, or its pages can skip and repeat rows.');
    }

    public static function pagedChunk(): self
    {
        return new self(message: 'A chunked query cannot limit or page itself; chunk() pages it.');
    }

    public static function insertGetIdRowCount(int $rows): self
    {
        return new self(message: 'An insert that returns a key must have exactly one row.', context: ['rows' => $rows]);
    }

    public static function nullComparison(string $operator): self
    {
        return new self(message: sprintf('The operator "%s" cannot be used with NULL.', $operator), context: [
            'operator' => $operator,
        ]);
    }

    public static function nullHaving(): self
    {
        return new self(message: 'A having condition cannot compare to NULL.');
    }

    public static function groupedAggregate(string $function): self
    {
        return new self(
            message: sprintf('A grouped query has one %s per group; add it to the selection instead.', $function),
            context: ['function' => $function],
        );
    }

    public static function offsetMutation(string $operation): self
    {
        return new self(message: sprintf('%s queries cannot skip rows with an offset.', ucfirst($operation)), context: [
            'operation' => $operation,
        ]);
    }

    public static function invalidBulkInsert(): self
    {
        return new self(message: 'Bulk inserts must contain arrays of column values.');
    }

    public static function emptyInsert(): self
    {
        return new self(message: 'An insert needs at least one row.');
    }

    public static function insertWithoutColumns(): self
    {
        return new self(message: 'An insert needs at least one column.');
    }

    public static function mismatchedInsertColumns(): self
    {
        return new self(message: 'Every inserted row needs the same columns in the same order.');
    }

    public static function emptyUpdate(): self
    {
        return new self(message: 'An update needs at least one value.');
    }

    public static function invalidBinding(string $type): self
    {
        return new self(message: sprintf('A query binding must be scalar or null, got "%s".', $type), context: [
            'type' => $type,
        ]);
    }
}
