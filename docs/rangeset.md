# RangeSet Guide

Back to index: `docs/README.md`

`IPTools\RangeSet` is a canonical set of disjoint ranges with set algebra operations.

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

## Input Forms

Each operation accepts combinations of:

- `RangeSet`
- `iterable` of items
- `Range`
- `Network`
- `IP`
- `string` parsable by `Range::parse`

## Operations

- `union(...)`: combine sets and normalize
- `intersect(...)`: keep only overlaps
- `subtract(...)`: remove portions from the set
- `contains(IP $ip): bool`
- `containsRange(...): bool`
- `overlaps(...): bool`
- `toCidrs(): Network[]`
- `getRanges(): Range[]`

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

## Version Isolation

IPv4 and IPv6 ranges are normalized independently. Operations do not cross-compare versions.

This prevents mixed-version ordering errors and keeps behavior deterministic.
