<?php

declare(strict_types=1);

namespace IPTools\Storage;

use Illuminate\Database\Connection;
use IPTools\IP;
use IPTools\Network;
use IPTools\Range;
use PDO;

/**
 * Laravel adapter that bridges Illuminate\Database\Connection to SqlRangeStorage.
 *
 * Delegates all operations to an underlying SqlRangeStorage instance, lazily
 * created from the Connection's PDO handle. The PDO instance is tracked so
 * the storage is rebuilt if Laravel reconnects (new PDO object identity).
 */
final class LaravelRangeStorage implements RangeStorageInterface
{
    private ?PDO $pdo = null;

    private ?SqlRangeStorage $storage = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly string $table = 'ip_ranges',
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
     * @return iterable<array{range: Range, metadata: array<string, mixed>}>
     */
    public function findContaining(IP $ip): iterable
    {
        return $this->storage()->findContaining($ip);
    }

    public function count(): int
    {
        return $this->storage()->count();
    }

    /**
     * Lazily create (or refresh) the underlying SqlRangeStorage.
     *
     * Compares PDO object identity to detect reconnects — if Laravel's
     * Connection has been reconnected, $connection->getPdo() returns a
     * new instance, and we must rebuild SqlRangeStorage to use it.
     */
    private function storage(): SqlRangeStorage
    {
        $pdo = $this->connection->getPdo();

        if (! $this->storage instanceof SqlRangeStorage || $this->pdo !== $pdo) {
            $this->pdo = $pdo;
            $this->storage = new SqlRangeStorage($pdo, $this->table);
        }

        return $this->storage;
    }
}
