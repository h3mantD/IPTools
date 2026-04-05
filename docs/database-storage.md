# Database Storage

- [Documentation](README.md)

IPTools includes an optional storage layer for persisting IP ranges in a database. This lets you do server-side lookups like "which stored ranges contain this IP?" — useful for geo-IP, allow/deny lists, rate limiting, and IP reputation systems.

> **Note:** If you only need in-memory operations (parsing, arithmetic, set algebra), you don't need this module at all. Everything in the [IP](ip-addresses.md), [Network](networks.md), [Range](ranges.md), and [RangeSet](range-sets.md) guides works without a database.

## When You Need Database Storage

Use it when:

- You have IP ranges that need to persist across requests
- You need to look up "which ranges contain this IP?" from a database
- You want to attach metadata (org name, policy, priority) to ranges
- Your range data comes from external feeds that update independently

Skip it when:

- Your ranges are hardcoded or loaded from config
- You only do in-memory checks during a single request
- You're already using `RangeSet` for everything

## Schema Setup

Create the `ip_ranges` table in your database. Here are copy-paste schemas for common databases:

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

### SQLite

```sql
CREATE TABLE ip_ranges (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  version INTEGER NOT NULL,
  start_bin BLOB NOT NULL,
  end_bin BLOB NOT NULL,
  metadata TEXT NULL
);

CREATE INDEX idx_lookup ON ip_ranges (version, start_bin, end_bin);
```

> **Tip:** The composite index on `(version, start_bin, end_bin)` is critical for performance. Without it, containment queries do full table scans.

> **Note:** If you're using Laravel, you don't need to create the table manually — publish and run the included migration instead. See the [Laravel Integration](laravel.md) guide.

## How Addresses Are Encoded

All addresses are stored as **16-byte binary** values, regardless of version:

- **IPv4** addresses are stored as 12 zero bytes + 4 address bytes
- **IPv6** addresses are stored as their full 16 bytes

This normalization means a single `BINARY(16)` column handles both versions, and containment queries use simple binary comparisons: `start_bin <= address AND end_bin >= address`.

The `version` column (4 or 6) prevents cross-version false matches and lets you filter by protocol when needed.

## Basic Usage

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Range;
use IPTools\Storage\SqlRangeStorage;

// Create storage with a PDO connection
$storage = new SqlRangeStorage($pdo, 'ip_ranges');
```

### Storing Ranges

```php
$storage->store(Network::parse('10.0.0.0/24'), ['name' => 'office', 'policy' => 'allow']);
$storage->store(Range::parse('192.168.1.100-192.168.1.200'), ['name' => 'dhcp-pool']);
$storage->store(Network::parse('2001:db8::/32'), ['name' => 'docs-v6']);
```

### Checking Containment

```php
$storage->contains(new IP('10.0.0.42'));     // true
$storage->contains(new IP('8.8.8.8'));       // false
```

### Finding All Matching Ranges

```php
foreach ($storage->findContaining(new IP('10.0.0.42')) as $match) {
    $range = $match['range'];       // IPTools\Range object
    $metadata = $match['metadata']; // array

    echo "{$range->getFirstIP()}-{$range->getLastIP()}: {$metadata['name']}";
}
```

An IP may fall within multiple stored ranges — `findContaining()` returns all of them.

### Deleting Ranges

```php
$storage->delete(Network::parse('10.0.0.0/24'));
```

Deletion matches on exact start/end/version boundaries.

### Counting

```php
echo $storage->count(); // total number of stored ranges
```

## Working With Metadata

Metadata is stored as JSON. You can attach any JSON-serializable data:

```php
$storage->store(Network::parse('10.0.0.0/24'), [
    'organization' => 'ACME Corp',
    'policy' => 'allow',
    'tags' => ['internal', 'vpn'],
    'priority' => 10,
]);
```

When no metadata is needed, just omit the second argument:

```php
$storage->store(Network::parse('192.0.2.0/24'));
// metadata will be an empty array when retrieved
```

> **Note:** Metadata is stored as JSON text. Deeply nested structures and Unicode are supported, but the values must be JSON-serializable (no resources, closures, or circular references).

## Performance Tips

- **Always create the composite index.** The `(version, start_bin, end_bin)` index is required for efficient containment queries.
- **Use `contains()` for hot paths.** It returns a simple boolean with a `LIMIT 1` query.
- **Use `findContaining()` when you need details.** It streams results, so memory stays flat even with many matches.
- **Separate hot and cold data.** If you have millions of ranges but only query a subset, consider partitioning by version or adding application-level filters.

## Troubleshooting

### "Table 'ip_ranges' is not available"

The storage layer checks for table existence on first use. If the table doesn't exist, you'll get a `RuntimeException` with setup instructions. Create the table using the SQL schemas above, or if you're using Laravel, run:

```bash
php artisan vendor:publish --tag=iptools-migrations
php artisan migrate
```

### "Invalid table name"

Table names must match `[A-Za-z_]\w*` — letters, digits, and underscores only. No spaces, dashes, or special characters.

### Metadata Returns Empty Array

If you stored a range without metadata (or with `null`), `findContaining()` returns an empty array `[]` for the metadata field. This is by design — it normalizes the output so you don't need null checks.

### Contains Returns False for IPv4 When IPv6 Was Stored (or Vice Versa)

Containment queries are version-scoped. An IPv4 address will never match an IPv6 range, even if they represent similar numeric values. This is intentional.

## What's Next?

- **[Laravel Integration](laravel.md)** — Use the storage layer with Laravel's container, migrations, and Eloquent
- **[Range Sets](range-sets.md)** — For in-memory set algebra without a database
- **[API Reference](api-reference.md)** — Full method listing for `SqlRangeStorage`, `LaravelRangeStorage`, and `AddressCodec`
