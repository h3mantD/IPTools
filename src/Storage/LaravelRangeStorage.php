<?php

declare(strict_types=1);

namespace IPTools\Storage;

use Illuminate\Database\Connection;
use IPTools\IP;
use IPTools\Network;
use IPTools\Range;

final readonly class LaravelRangeStorage implements RangeStorageInterface
{
    public function __construct(
        private Connection $connection,
        private string $table = 'ip_ranges',
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(Network|Range $range, array $metadata = []): void
    {
        $this->storage()->store($range, $metadata);
    }

    public function delete(Network|Range $range): void
    {
        $this->storage()->delete($range);
    }

    public function contains(IP $ip): bool
    {
        return $this->storage()->contains($ip);
    }

    /**
     * @return iterable<array{range: Network|Range, metadata: array<string, mixed>}>
     */
    public function findContaining(IP $ip): iterable
    {
        return $this->storage()->findContaining($ip);
    }

    public function count(): int
    {
        return $this->storage()->count();
    }

    private function storage(): SqlRangeStorage
    {
        return new SqlRangeStorage($this->connection->getPdo(), $this->table);
    }
}
