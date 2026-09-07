<?php

namespace Dirthara\Database\Connection\Result;

use PDO;
use PDOStatement;
use InvalidArgumentException;

final readonly class PdoResult implements Result
{
    public function __construct(
        private PDOStatement $statement,
    ) {}

    public function first(): ?array
    {
        $row = $this->statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function all(): array
    {
        /** @var list<array<string, mixed>> $rows */
        $rows = $this->statement->fetchAll(PDO::FETCH_ASSOC);

        return $rows;
    }

    public function column(int|string $column = 0): array
    {
        if (is_int($column)) {
            return $this->fetchColumnByInt($column);
        }

        return $this->fetchColumnByName($column);
    }

    public function affectedRows(): int
    {
        return $this->statement->rowCount();
    }

    public function iterate(): iterable
    {
        while (($row = $this->statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            yield $row;
        }
    }

    private function fetchColumnByInt(int $column): array
    {
        /** @var list<mixed> $values */
        $values = $this->statement->fetchAll(PDO::FETCH_COLUMN, $column);

        return $values;
    }

    private function fetchColumnByName(string $column): array
    {
        $values = [];

        foreach ($this->iterate() as $row) {
            if (!array_key_exists($column, $row)) {
                throw new InvalidArgumentException(sprintf('Column %s does not exist in the result.', $column));
            }

            $values[] = $row[$column];
        }

        return $values;
    }
}
