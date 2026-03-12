# Range Guide

Back to index: `docs/README.md`

`IPTools\Range` models inclusive `[firstIP, lastIP]` intervals.

## Parsing

```php
use IPTools\Range;

$r1 = Range::parse('192.168.1.0-192.168.1.255');
$r2 = Range::parse('192.168.1.0/24');
$r3 = Range::parse('192.168.1.*');
$r4 = Range::parse('2001:db8::1-2001:db8::ff');
```

## Containment

`contains` accepts `IP|Range|Network`.

```php
var_dump($r1->contains(new IPTools\IP('192.168.1.10')));
```

## Range to Network Decomposition

- `getNetworks(): Network[]`
- `iterateNetworks(): Generator<Network>`

These APIs decompose a range into minimal covering CIDR blocks.

Selector helpers:

- `getFirstNetwork()`
- `getLastNetwork()`
- `getNthNetwork(int $index)`

## Address Indexing

- `addressAt(int|string $offset): ?IP`
- `addressAtOrFail(int|string $offset): IP`

Offset behavior:

- `0` means first address
- positive offsets move forward
- negative offsets count from end (`-1` is last address)

## Span Helper

`getSpanNetwork()` returns the smallest single network that spans the full range (can include extra addresses outside the range).

## Counting and Iteration

- `getCountPrecise(): numeric-string`
- `count(): int` (clamped)
- Iterator support over every address in the interval

For large IPv6 ranges, use `getCountPrecise` and network decomposition APIs instead of direct full iteration.
