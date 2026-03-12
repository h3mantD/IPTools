# Storage Guide

Back to index: [Documentation Index](README.md)

IPTools includes optional DB-backed range storage for fast membership lookups.

Core types:

- `IPTools\Storage\RangeStorageInterface`
- `IPTools\Storage\SqlRangeStorage`
- `IPTools\Storage\AddressCodec`

## Optional Feature

Storage is optional. You only need it when ranges must be persisted and queried from a database.

## SQL Schema

Required columns:

- `version` (`4` or `6`)
- `start_bin` (`16` bytes)
- `end_bin` (`16` bytes)
- `metadata` (JSON/text)

Recommended index:

- `(version, start_bin, end_bin)`

## Address Encoding

`AddressCodec` normalizes all addresses to fixed 16-byte values.

- IPv4 encoded as `12` zero bytes + IPv4 `in_addr`
- IPv6 encoded as full 16-byte `in_addr`

This allows lexicographic range comparisons in SQL.

## SqlRangeStorage API

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\SqlRangeStorage;

$storage = new SqlRangeStorage($pdo, 'ip_ranges');

$storage->store(Network::parse('192.0.2.0/24'), ['name' => 'example']);
var_dump($storage->contains(new IP('192.0.2.10'))); // true

foreach ($storage->findContaining(new IP('192.0.2.10')) as $row) {
    $range = $row['range'];
    $metadata = $row['metadata'];
}

$storage->delete(Network::parse('192.0.2.0/24'));
echo $storage->count();
```

## Error Behavior

- Constructor validates table name format.
- If table is missing, methods throw a clear `RuntimeException`.
- Metadata is JSON-encoded/decoded with strict JSON exceptions.

## Performance Notes

- Always create the `(version, start_bin, end_bin)` index.
- Keep data normalized through `store` APIs.
- Use `findContaining` when overlaps matter and `contains` for existence checks.
