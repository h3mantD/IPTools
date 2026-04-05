# Range Sets

- [Documentation](README.md)

The `RangeSet` class manages a canonical collection of non-overlapping IP ranges. When you add overlapping or adjacent ranges, they're automatically merged. When you need to combine, diff, or intersect address blocks, this is the tool to reach for.

```php
use IPTools\RangeSet;

$set = new RangeSet([
    '10.0.0.0-10.0.0.10',
    '10.0.0.11-10.0.0.20',
    '10.0.0.5-10.0.0.8',    // overlaps with first range
]);

count($set->getRanges()); // 1 — all three merged into 10.0.0.0-10.0.0.20
```

## Why RangeSet?

If you have a flat list of IP ranges from different sources (firewall rules, allow-lists, deny-lists), they'll often overlap or sit next to each other. `RangeSet` normalizes them on construction so you always work with the minimal, canonical representation.

Normalization guarantees:

- Ranges are sorted by start address
- Overlapping ranges are merged
- Adjacent ranges are merged (e.g., `.0-.10` and `.11-.20` become `.0-.20`)
- IPv4 and IPv6 are normalized independently

> **Warning:** Every operation (`union`, `intersect`, `subtract`) returns a **new** `RangeSet`. The original is never modified.

## Creating Sets

The constructor and `from()` factory both accept flexible input — strings, objects, or a mix:

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Range;
use IPTools\RangeSet;

$set = new RangeSet([
    '10.0.0.0/24',                          // string (CIDR)
    '10.0.1.0-10.0.1.255',                  // string (range)
    new IP('10.0.2.1'),                      // single IP
    Range::parse('10.0.3.0-10.0.3.255'),     // Range object
    Network::parse('10.0.4.0/24'),           // Network object
]);

// Or use the static factory
$set = RangeSet::from(['10.0.0.0/24', '10.0.1.0/24']);
```

> **Tip:** All set operations also accept this flexible input. You can pass a string, an `IP`, a `Range`, a `Network`, another `RangeSet`, or an iterable of any of these.

## Set Operations

### Union

Combine two sets, merging any overlaps:

```php
$internal = new RangeSet(['10.0.0.0/24']);
$vpn = new RangeSet(['10.0.1.0/24']);

$all = $internal->union($vpn);
// 2 ranges: 10.0.0.0-10.0.0.255, 10.0.1.0-10.0.1.255

// Adjacent ranges get merged automatically
$adjacent = new RangeSet(['10.0.0.0/24']);
$merged = $adjacent->union('10.0.1.0/24');
// 1 range: 10.0.0.0-10.0.1.255
```

### Intersect

Keep only the address space common to both sets:

```php
$a = new RangeSet(['10.0.0.0-10.0.0.20']);
$b = new RangeSet(['10.0.0.10-10.0.0.30']);

$common = $a->intersect($b);
// 1 range: 10.0.0.10-10.0.0.20
```

If the sets don't overlap, you get an empty `RangeSet`:

```php
$disjoint = $a->intersect('192.168.0.0/24');
count($disjoint); // 0
```

### Subtract

Remove a portion from the set:

```php
$full = new RangeSet(['10.0.0.0-10.0.0.20']);

$result = $full->subtract('10.0.0.5-10.0.0.10');
// 2 ranges: 10.0.0.0-10.0.0.4, 10.0.0.11-10.0.0.20
```

Subtracting the entire range leaves an empty set:

```php
$empty = $full->subtract('10.0.0.0-10.0.0.255');
count($empty); // 0
```

## Containment and Overlap Queries

```php
$set = new RangeSet(['10.0.0.0/24']);

// Does this single IP fall within the set?
$set->contains(new IP('10.0.0.42'));       // true
$set->contains(new IP('10.0.1.1'));        // false

// Is this entire range fully contained?
$set->containsRange('10.0.0.10-10.0.0.20'); // true
$set->containsRange('10.0.0.0/23');          // false (extends beyond /24)

// Does this range overlap at all?
$set->overlaps('10.0.0.200-10.0.1.1');    // true (partial overlap)
$set->overlaps('10.0.2.0/24');            // false (no overlap)
```

## Converting to CIDRs

When you need to deploy the result as router or firewall rules, convert to the minimum CIDR set:

```php
$allow = new RangeSet(['10.0.0.0/24', '10.0.1.0/24']);
$deny = new RangeSet(['10.0.0.100-10.0.0.200']);

$effective = $allow->subtract($deny);

foreach ($effective->toCidrs() as $cidr) {
    echo $cidr . PHP_EOL;
    // Feed to your firewall/router config
}
```

## Mixed IPv4 and IPv6

A `RangeSet` can hold both IPv4 and IPv6 ranges simultaneously. They're normalized independently and never interact:

```php
$mixed = new RangeSet([
    '10.0.0.0/24',
    '2001:db8::/126',
]);

$mixed->contains(new IP('10.0.0.42'));    // true
$mixed->contains(new IP('2001:db8::2'));  // true
$mixed->contains(new IP('192.168.1.1')); // false

// Subtracting IPv4 doesn't affect IPv6
$result = $mixed->subtract('10.0.0.0/25');
$result->contains(new IP('2001:db8::2'));  // still true
$result->contains(new IP('10.0.0.42'));    // false (removed)
$result->contains(new IP('10.0.0.200'));   // true (kept)
```

> **Note:** Operations never cross-compare versions. An IPv4 range will never overlap with an IPv6 range, even if they represent similar numeric values.

## Practical Patterns

### Allow/Deny Policy Engine

```php
$allow = new RangeSet([
    '10.0.0.0/8',
    '172.16.0.0/12',
    '192.168.0.0/16',
]);

$deny = new RangeSet([
    '10.0.99.0/24',        // compromised subnet
    '192.168.1.100-192.168.1.200', // DHCP pool
]);

$effective = $allow->subtract($deny);

function isAllowed(string $ipAddress): bool {
    global $effective;
    return $effective->contains(new IP($ipAddress));
}
```

### Merging Rules From Multiple Sources

```php
$fromConfig = new RangeSet($configRanges);
$fromDatabase = new RangeSet($dbRanges);
$fromApi = new RangeSet($apiRanges);

$combined = $fromConfig->union($fromDatabase)->union($fromApi);
// All overlaps and adjacencies resolved
```

## Troubleshooting

### Empty Set After Operations

If `union`, `intersect`, or `subtract` returns an empty set, verify that your input ranges are in the expected format. A common mistake is passing a single string that should be an array:

```php
// Wrong: treats the whole string as one range
$set = new RangeSet('10.0.0.0/24');

// Correct: pass an array
$set = new RangeSet(['10.0.0.0/24']);
```

### IPv4/IPv6 Containment Always Returns False

Make sure you're querying with the same version. `contains()` with an IPv6 address will never match IPv4 ranges and vice versa.

## What's Next?

- **[Ranges](ranges.md)** — For working with individual start/end intervals
- **[Database Storage](database-storage.md)** — Persist ranges for server-side lookups
- **[Comparison Guide](comparison.md)** — When to use `RangeSet` vs `Range` vs `Network`
