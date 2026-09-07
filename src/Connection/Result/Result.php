<?php

declare(strict_types=1);

namespace Dirthara\Database\Connection\Result;

use Dirthara\Database\Connection\Exceptions\ResultException;

interface Result
{
    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array;

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array;

    /**
     * @return list<mixed>
     *
     * @throws ResultException
     */
    public function column(int|string $column = 0): array;

    public function affectedRows(): int;

    /**
     * @return iterable<array<string, mixed>>
     */
    public function iterate(): iterable;
}
