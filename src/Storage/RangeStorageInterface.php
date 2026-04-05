<?php

declare(strict_types=1);

namespace IPTools\Storage;

use IPTools\IP;
use IPTools\Network;
use IPTools\Range;

/**
 * Persistent storage for IP ranges with optional metadata.
 *
 * Implementations handle the encoding and querying of IP ranges in a
 * backing store (database, file, etc.). Each stored range carries an
 * arbitrary metadata array (e.g., organization name, policy, priority).
 *
 * @see SqlRangeStorage     PDO-based implementation (MySQL, PostgreSQL, SQLite).
 * @see LaravelRangeStorage Laravel adapter wrapping SqlRangeStorage.
 */
interface RangeStorageInterface
{
    /**
     * Persist a range with optional metadata. Duplicates are allowed.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function store(Network|Range $range, array $metadata = []): void;

    /** Remove the range matching the exact start/end/version. */
    public function delete(Network|Range $range): void;

    /** Check if an IP falls within any stored range. */
    public function contains(IP $ip): bool;

    /**
     * Return all stored ranges that contain the given IP, with their metadata.
     *
     * @return iterable<array{range: Range, metadata: array<string, mixed>}>
     */
    public function findContaining(IP $ip): iterable;

    /** Total number of stored ranges. */
    public function count(): int;
}
