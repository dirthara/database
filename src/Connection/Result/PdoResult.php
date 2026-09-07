<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Result;

use PDO;
use PDOStatement;
use Dirthara\Database\Connection\Exceptions\ResultException;

use function is_int;
use function is_array;

final readonly class PdoResult implements Result
{
    public function __construct(
        private PDOStatement $statement,
    ) {}

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        /** @var array<string, mixed>|false $row */
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        // Keep the variable so the analyzer can apply the PDO fetch-mode type.
        /** @var list<array<string, mixed>> $rows */
        // @mago-expect lint:inline-variable-return
        $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    /**
     * @return list<mixed>
     *
     * @throws ResultException
     */
    public function column(int|string $column = 0): array
    {
        $index = is_int($column) ? $this->assertPosition($column) : $this->resolvePosition($column);

        // Keep the variable so the analyzer can apply the PDO fetch-mode type.
        /** @var list<mixed> $values */
        // @mago-expect lint:inline-variable-return
        $values = $this->statement->fetchAll(PDO::FETCH_COLUMN, $index);

        return $values;
    }

    public function affectedRows(): int
    {
        return $this->statement->rowCount();
    }

    /**
     * @return iterable<array<string, mixed>>
     */
    public function iterate(): iterable
    {
        while (($row = $this->first()) !== null) {
            yield $row;
        }
    }

    /**
     * @throws ResultException
     */
    private function assertPosition(int $column): int
    {
        if ($column < 0 || $column >= $this->statement->columnCount()) {
            throw ResultException::unknownColumn($column, $this->columnNames());
        }

        return $column;
    }

    /**
     * @throws ResultException
     */
    private function resolvePosition(string $column): int
    {
        $names = $this->columnNames();

        if ($names === [] && $this->statement->columnCount() > 0) {
            throw ResultException::columnMetadataUnavailable($column);
        }

        foreach ($names as $index => $name) {
            if ($name === $column) {
                return $index;
            }
        }

        throw ResultException::unknownColumn($column, $names);
    }

    /**
     * @return array<int, string>
     */
    private function columnNames(): array
    {
        $names = [];
        $count = $this->statement->columnCount();

        for ($index = 0; $index < $count; $index++) {
            $meta = $this->statement->getColumnMeta($index);

            if (is_array($meta)) {
                $names[$index] = $meta['name'];
            }
        }

        return $names;
    }
}
