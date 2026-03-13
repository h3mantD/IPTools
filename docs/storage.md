# Storage Guide

Back to index: [Documentation Index](README.md)

IPTools includes optional DB-backed range storage for fast membership lookups.

This layer is intentionally framework-agnostic. You can use it in plain PHP, Laravel, or custom runtimes.

Core types:

- `IPTools\Storage\RangeStorageInterface`
- `IPTools\Storage\SqlRangeStorage`
- `IPTools\Storage\AddressCodec`

## Optional Feature

Storage is optional. You only need it when ranges must be persisted and queried from a database.

If your workflow is fully in-memory, you can skip this entire section.

## SQL Schema

Required columns:

- `version` (`4` or `6`)
- `start_bin` (`16` bytes)
- `end_bin` (`16` bytes)
- `metadata` (JSON/text)

Recommended index:

- `(version, start_bin, end_bin)`

This index is critical for scalable lookup performance.

### Example schemas

MySQL:

```sql
CREATE TABLE ip_ranges (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  version TINYINT NOT NULL,
  start_bin BINARY(16) NOT NULL,
  end_bin BINARY(16) NOT NULL,
  metadata JSON NULL,
  KEY idx_lookup (version, start_bin, end_bin)
) ENGINE=InnoDB;
```

PostgreSQL:

```sql
CREATE TABLE ip_ranges (
  id BIGSERIAL PRIMARY KEY,
  version SMALLINT NOT NULL,
  start_bin BYTEA NOT NULL,
  end_bin BYTEA NOT NULL,
  metadata JSONB NULL
);

CREATE INDEX idx_lookup ON ip_ranges (version, start_bin, end_bin);
```

## Address Encoding

`AddressCodec` normalizes all addresses to fixed 16-byte values.

- IPv4 encoded as `12` zero bytes + IPv4 `in_addr`
- IPv6 encoded as full 16-byte `in_addr`

This allows lexicographic range comparisons in SQL.

Encoding and decoding helpers are exposed via `AddressCodec`:

- `AddressCodec::to16(IP $ip): string`
- `AddressCodec::from16(string $bin, int $version): IP`

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

Method semantics:

- `store(...)` inserts one range row with optional metadata
- `delete(...)` removes exact matching range boundaries
- `contains(IP $ip)` returns boolean existence
- `findContaining(IP $ip)` yields all matching ranges + metadata
- `count()` returns row count in the backing table

`findContaining()` payload shape:

```php
[
    'range' => IPTools\Range,
    'metadata' => array<string, mixed>,
]
```

## Error Behavior

- Constructor validates table name format.
- If table is missing, methods throw a clear `RuntimeException`.
- Metadata is JSON-encoded/decoded with strict JSON exceptions.

Common failure sources:

- table not migrated
- wrong table name in config
- malformed metadata values not JSON-encodable

## Performance Notes

- Always create the `(version, start_bin, end_bin)` index.
- Keep data normalized through `store` APIs.
- Use `findContaining` when overlaps matter and `contains` for existence checks.

## Recommended Usage Pattern

1. Migrate/create table and index.
2. Bulk-insert ranges.
3. Use `contains` on hot paths.
4. Use `findContaining` when policy precedence or metadata inspection is needed.
5. Periodically validate row counts and index health.
