# Ranges

- [Documentation](README.md)

The `Range` class represents an inclusive interval from a first IP to a last IP. Use it when your data is naturally expressed as start/end addresses rather than CIDR prefixes — DHCP pools, allocation blocks, or arbitrary address spans.

> **Tip:** Not sure whether to use `Range` or `Network`? If your boundaries align to a CIDR prefix, use `Network`. If they don't (or you're not sure), use `Range`. See the [Comparison Guide](comparison.md) for more detail.

## Creating Ranges

Ranges can be created from several formats:

```php
use IPTools\Range;
use IPTools\IP;

// Dash-separated endpoints
$range = Range::parse('192.168.1.0-192.168.1.255');

// CIDR notation (expands to full range)
$range = Range::parse('192.168.1.0/24');

// Wildcard (IPv4 only)
$range = Range::parse('192.168.*.*');

// Single IP (one-address range)
$range = Range::parse('10.0.0.1');

// Direct construction
$range = new Range(new IP('10.0.0.1'), new IP('10.0.0.100'));
```

IPv6 works the same way:

```php
$range = Range::parse('2001:db8::1-2001:db8::ff');
```

## Containment Checks

The `contains()` method accepts `IP`, `Network`, or `Range` objects:

```php
$range = Range::parse('10.0.0.0-10.0.0.255');

$range->contains(new IP('10.0.0.100'));              // true
$range->contains(new IP('10.0.1.0'));                // false
$range->contains(Network::parse('10.0.0.0/25'));     // true (fully contained)
$range->contains(Range::parse('10.0.0.10-10.0.0.20')); // true
```

Containment is inclusive at both boundaries — the first and last IPs are part of the range.

## CIDR Decomposition

A range that doesn't align to a single CIDR block can be decomposed into the minimum set of CIDR networks that cover it exactly:

```php
$range = Range::parse('192.168.1.0-192.168.1.255');
$networks = $range->getNetworks();
// [Network('192.168.1.0/24')]  — perfect alignment

$range = Range::parse('192.168.1.1-192.168.1.254');
$networks = $range->getNetworks();
// 14 networks: 192.168.1.1/32, 192.168.1.2/31, ... 192.168.1.254/32
```

> **Tip:** For large ranges, use `iterateNetworks()` instead of `getNetworks()`. It returns a lazy `Generator` so you don't materialize the entire array in memory.

```php
foreach ($range->iterateNetworks() as $network) {
    // process one CIDR at a time
}
```

## Network Selectors

When you only need specific networks from the decomposition:

```php
$range = Range::parse('49.12.11.10-49.12.11.35');

echo $range->getFirstNetwork(); // 49.12.11.10/31
echo $range->getLastNetwork();  // 49.12.11.32/30
echo $range->getNthNetwork(2);  // 49.12.11.16/28
echo $range->getNthNetwork(99); // null (out of bounds)
```

These are useful for chunked export and pagination workflows.

## Address Indexing

Access individual addresses by offset:

```php
$range = Range::parse('10.0.0.10-10.0.0.20');

echo $range->addressAt(0);   // 10.0.0.10  (first)
echo $range->addressAt(5);   // 10.0.0.15
echo $range->addressAt(10);  // 10.0.0.20  (last)
echo $range->addressAt(11);  // null        (out of range)
```

> **Note:** Negative offsets count from the end. `-1` is the last address, `-2` is second-to-last, and so on.

```php
echo $range->addressAt(-1);  // 10.0.0.20
echo $range->addressAt(-11); // 10.0.0.10
echo $range->addressAt(-12); // null
```

If you want an exception instead of `null` for out-of-range offsets:

```php
$range->addressAtOrFail(100); // throws OutOfBoundsException
```

## Span Network

`getSpanNetwork()` returns the smallest single CIDR that fully contains the range. It may include extra addresses outside the range:

```php
$range = Range::parse('10.0.0.1-10.0.0.2');
echo $range->getSpanNetwork(); // 10.0.0.0/30 (covers .0 through .3)

$range = Range::parse('10.0.0.0-10.0.0.255');
echo $range->getSpanNetwork(); // 10.0.0.0/24 (exact fit)
```

This is useful when you need one coarse covering block quickly, and extra coverage is acceptable.

## Counting and Iteration

```php
$range = Range::parse('10.0.0.0-10.0.0.255');

count($range);              // 256
$range->getCountPrecise();  // '256'
```

Iterate over every IP in the range:

```php
$small = Range::parse('10.0.0.0-10.0.0.3');
foreach ($small as $ip) {
    echo $ip . PHP_EOL;
}
// 10.0.0.0
// 10.0.0.1
// 10.0.0.2
// 10.0.0.3
```

> **Warning:** For large ranges, use `getCountPrecise()` and network decomposition instead of full iteration. The same caution applies as with large networks.

## Practical Patterns

### Chunked Export to CIDR

Turn an arbitrary range into route-sized CIDR chunks:

```php
$range = Range::parse('49.12.11.10-49.12.11.35');

foreach ($range->iterateNetworks() as $network) {
    echo $network . PHP_EOL;
}
// 49.12.11.10/31
// 49.12.11.12/30
// 49.12.11.16/28
// 49.12.11.32/30
```

### Access Control Check

```php
$dhcpPool = Range::parse('192.168.1.100-192.168.1.200');

if ($dhcpPool->contains(new IP($clientIP))) {
    echo "IP is in the DHCP pool";
}
```

### Quick Range Stats

```php
$range = Range::parse($input);

echo "First: {$range->getFirstIP()}";
echo "Last: {$range->getLastIP()}";
echo "Size: {$range->getCountPrecise()} addresses";
echo "Spans: {$range->getSpanNetwork()}";
```

## Troubleshooting

### "Last IP version does not match first IP version"

Both endpoints must be the same version. You can't create a range from an IPv4 to an IPv6 address.

### "Last IP is less than first"

The first IP must be less than or equal to the last IP. Swap them if needed.

### "Offset must be an integer string"

`addressAt()` accepts `int` or `string`, but the string must contain only digits (optionally with a leading `-`). No decimals, no spaces.

## What's Next?

- **[Range Sets](range-sets.md)** — Merge, diff, and intersect collections of ranges
- **[Networks](networks.md)** — If your boundaries align to CIDR, Network is more efficient
- **[API Reference](api-reference.md)** — Full method listing for the `Range` class
