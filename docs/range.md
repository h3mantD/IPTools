# Range Guide

Back to index: [Documentation Index](README.md)

`IPTools\Range` models inclusive `[firstIP, lastIP]` intervals.

Use `Range` when your data is naturally expressed as start/end addresses, not fixed prefixes.

## Parsing

```php
use IPTools\Range;

$r1 = Range::parse('192.168.1.0-192.168.1.255');
$r2 = Range::parse('192.168.1.0/24');
$r3 = Range::parse('192.168.1.*');
$r4 = Range::parse('2001:db8::1-2001:db8::ff');
```

Accepted forms:

- explicit range: `start-end`
- CIDR/netmask expression
- wildcard IPv4 expression
- single address (becomes one-address range)

## Containment

`contains` accepts `IP|Range|Network`.

```php
var_dump($r1->contains(new IPTools\IP('192.168.1.10')));
```

Containment is inclusive at both boundaries.

## Range to Network Decomposition

- `getNetworks(): Network[]`
- `iterateNetworks(): Generator<Network>`

These APIs decompose a range into minimal covering CIDR blocks.

Selector helpers:

- `getFirstNetwork()`
- `getLastNetwork()`
- `getNthNetwork(int $index)`

These are useful for lazy pagination and chunked export workflows.

## Address Indexing

- `addressAt(int|string $offset): ?IP`
- `addressAtOrFail(int|string $offset): IP`

Offset behavior:

- `0` means first address
- positive offsets move forward
- negative offsets count from end (`-1` is last address)

```php
$range = Range::parse('10.0.0.10-10.0.0.20');
echo $range->addressAt(0);   // 10.0.0.10
echo $range->addressAt(5);   // 10.0.0.15
echo $range->addressAt(-1);  // 10.0.0.20
```

Use `addressAtOrFail` when out-of-range should be exceptional.

## Span Helper

`getSpanNetwork()` returns the smallest single network that spans the full range (can include extra addresses outside the range).

This is useful when you need one coarse covering block quickly.

## Counting and Iteration

- `getCountPrecise(): numeric-string`
- `count(): int` (clamped)
- Iterator support over every address in the interval

For large IPv6 ranges, use `getCountPrecise` and network decomposition APIs instead of direct full iteration.

## Practical Patterns

### Turn arbitrary range input into route-sized chunks

```php
$range = Range::parse('49.12.11.10-49.12.11.35');
foreach ($range->iterateNetworks() as $network) {
    // minimal CIDR chunks
}
```

### Efficient metadata/reporting

```php
$total = $range->getCountPrecise(); // exact numeric-string
```

### Safe boundary access

```php
$first = $range->getFirstIP();
$last = $range->getLastIP();
```
