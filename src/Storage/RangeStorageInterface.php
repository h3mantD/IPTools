<?php

declare(strict_types=1);

namespace IPTools\Storage;

use IPTools\IP;
use IPTools\Network;
use IPTools\Range;

interface RangeStorageInterface
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function store(Network|Range $range, array $metadata = []): void;

    public function delete(Network|Range $range): void;

    public function contains(IP $ip): bool;

    /**
     * @return iterable<array{range: Range, metadata: array<string, mixed>}>
     */
    public function findContaining(IP $ip): iterable;

    public function count(): int;
}
