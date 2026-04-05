# IPTools Documentation

IPTools is a PHP library for working with IPv4 and IPv6 addresses, networks, and ranges. Whether you're building firewall rules, validating user input, planning subnets, or storing IP ranges in a database — this library has you covered.

```php
use IPTools\IP;
use IPTools\Network;

$ip = new IP('192.168.1.1');

if ($ip->isPrivate()) {
    echo "{$ip} is a private address";
}

$network = Network::parse('10.0.0.0/24');

if ($network->containsIP($ip)) {
    echo "{$ip} is in the network";
}
```

## Not Sure Where to Start?

If you're new to IPTools, start with the **[Getting Started](getting-started.md)** guide. It walks you through installation and basic usage in under 5 minutes.

If you know what you need, jump to the relevant guide below.

If you're trying to decide between `IP`, `Network`, `Range`, or `RangeSet`, see the **[Comparison Guide](comparison.md)**.

## Guides

- **[Getting Started](getting-started.md)** — Install, parse your first IP, build a simple allow-list
- **[IP Addresses](ip-addresses.md)** — Parse, convert, classify, and do arithmetic on individual addresses
- **[Networks](networks.md)** — CIDR subnets, host boundaries, exclusion, splitting, and summarization
- **[Ranges](ranges.md)** — Inclusive intervals, CIDR decomposition, indexed access
- **[Range Sets](range-sets.md)** — Set algebra (union, intersect, subtract) on collections of ranges
- **[Parsing](parsing.md)** — Flexible input parsing with ports, zone IDs, wildcards, and flags
- **[Database Storage](database-storage.md)** — Persist and query IP ranges with SQL (MySQL, PostgreSQL, SQLite)
- **[Laravel Integration](laravel.md)** — Service provider, migrations, container bindings
- **[Comparison Guide](comparison.md)** — Which class should I use?
- **[API Reference](api-reference.md)** — Every public method at a glance

## Suggested Learning Path

1. **[Getting Started](getting-started.md)** — Get up and running
2. **[IP Addresses](ip-addresses.md)** — Understand the core value object
3. **[Networks](networks.md)** and **[Ranges](ranges.md)** — Work with blocks and intervals
4. **[Parsing](parsing.md)** — Handle real-world user input
5. **[Range Sets](range-sets.md)** — Build policy logic with set algebra
6. **[Database Storage](database-storage.md)** — Persist ranges for lookups
7. **[Laravel Integration](laravel.md)** — If your app uses Laravel

## Conventions

Throughout these docs:

- `IP` refers to `IPTools\IP`
- `Network` refers to `IPTools\Network`
- `Range` refers to `IPTools\Range`
- Code examples show output in comments: `// 192.168.1.0`
- Methods returning large numbers use `numeric-string` (a string containing a decimal number) because IPv6 values can exceed `PHP_INT_MAX`
- `count()` methods return `int` and may clamp at `PHP_INT_MAX` for very large IPv6 spaces — use `getCountPrecise()` when you need the exact value
