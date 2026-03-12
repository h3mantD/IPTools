# RangeSet Guide

Back to index: [Documentation Index](README.md)

`IPTools\RangeSet` is a canonical set of disjoint ranges with set algebra operations.

This is the preferred type when your domain involves merging, diffing, and intersecting many rules.

## Why RangeSet

When you have many overlapping/adjacent blocks, `RangeSet` normalizes them into a compact internal representation.

```php
use IPTools\RangeSet;

$set = new RangeSet([
    '10.0.0.0-10.0.0.10',
    '10.0.0.11-10.0.0.20',
]);

echo count($set); // 1 (adjacent ranges merged)
```

Normalization guarantees:

- sorted by start address
- overlaps merged
- adjacent ranges merged
- IPv4 and IPv6 normalized independently

## Input Forms

Each operation accepts combinations of:

- `RangeSet`
- `iterable` of items
- `Range`
- `Network`
- `IP`
- `string` parsable by `Range::parse`

This lets you combine strongly typed and raw user/config inputs in one operation.

## Operations

- `union(...)`: combine sets and normalize
- `intersect(...)`: keep only overlaps
- `subtract(...)`: remove portions from the set
- `contains(IP $ip): bool`
- `containsRange(...): bool`
- `overlaps(...): bool`
- `toCidrs(): Network[]`
- `getRanges(): Range[]`

All operations return a new normalized `RangeSet`.

## Examples

```php
use IPTools\IP;
use IPTools\RangeSet;

$base = new RangeSet(['10.0.0.0-10.0.0.20']);
$mask = new RangeSet(['10.0.0.5-10.0.0.10']);

$difference = $base->subtract($mask);
$intersection = $base->intersect($mask);
$union = $base->union('10.0.0.50-10.0.0.60');

var_dump($base->contains(new IP('10.0.0.8'))); // true
var_dump($base->containsRange('10.0.0.1-10.0.0.3')); // true
var_dump($base->overlaps('10.0.0.18-10.0.1.1')); // true
```

### Convert a policy result into deployable CIDRs

```php
$effective = $allow->subtract($deny);
foreach ($effective->toCidrs() as $cidr) {
    // feed firewall/router config
}
```

### Accept mixed input forms

```php
$set = new RangeSet([
    '10.0.0.0/24',
    new IPTools\IP('10.0.1.10'),
    IPTools\Range::parse('10.0.2.0-10.0.2.255'),
]);
```

## Version Isolation

IPv4 and IPv6 ranges are normalized independently. Operations do not cross-compare versions.

This prevents mixed-version ordering errors and keeps behavior deterministic.

## Complexity Notes

- Construction cost is dominated by sort + merge.
- Repeated operations are easiest to reason about when you keep a normalized `RangeSet` as your main state.
- Use `contains`/`containsRange` for fast membership checks against canonical data.
