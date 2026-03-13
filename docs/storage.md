# Storage

Back to index: [Documentation Index](README.md)

IPTools provides optional database-backed storage for persisted range lookups. If your application only performs in-memory operations, you can skip this guide.

The storage layer is framework-agnostic and works with plain PDO, Laravel, or custom runtimes.

## Available Classes

- `IPTools\Storage\RangeStorageInterface`
- `IPTools\Storage\SqlRangeStorage`
- `IPTools\Storage\AddressCodec`

## Database Schema

Your table should contain these columns:

- `version` (`4` or `6`)
- `start_bin` (`16` bytes)
- `end_bin` (`16` bytes)
- `metadata` (JSON-compatible payload)

You should also create the lookup index on `(version, start_bin, end_bin)`.

### MySQL

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

### PostgreSQL

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

`AddressCodec` converts all addresses to a fixed 16-byte representation, so SQL range comparisons remain deterministic.

- IPv4 is stored as `12` zero bytes + IPv4 `in_addr`
- IPv6 is stored as full 16-byte `in_addr`

Helper methods:

- `AddressCodec::to16(IP $ip): string`
- `AddressCodec::from16(string $bin, int $version): IP`

## Using SqlRangeStorage

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\SqlRangeStorage;

$storage = new SqlRangeStorage($pdo, 'ip_ranges');

$storage->store(Network::parse('192.0.2.0/24'), ['name' => 'example']);

$exists = $storage->contains(new IP('192.0.2.10')); // true

foreach ($storage->findContaining(new IP('192.0.2.10')) as $row) {
    $range = $row['range'];
    $metadata = $row['metadata'];
}

$storage->delete(Network::parse('192.0.2.0/24'));
$total = $storage->count();
```

Method behavior:

- `store(...)` inserts one range row with optional metadata
- `delete(...)` removes exact matching range boundaries
- `contains(IP $ip)` returns existence as `bool`
- `findContaining(IP $ip)` yields matching ranges with metadata
- `count()` returns row count

Payload returned by `findContaining`:

```php
[
    'range' => IPTools\Range,
    'metadata' => array<string, mixed>,
]
```

## Error Handling

- Constructor validates table name format.
- Missing tables throw a `RuntimeException` with setup guidance.
- Metadata serialization uses strict JSON behavior and can throw on invalid payloads.

Common setup issues:

- table not migrated
- incorrect table name
- metadata values not JSON-encodable

## Performance Notes

- Always create the `(version, start_bin, end_bin)` index.
- Use `contains` for hot-path existence checks.
- Use `findContaining` when overlap details or metadata are required.
