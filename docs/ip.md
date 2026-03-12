# IP Guide

Back to index: [Documentation Index](README.md)

`IPTools\IP` is the foundational value object in this package. Most other types (`Network`, `Range`, `RangeSet`) are built around it.

## Before You Start

- `IP` values are immutable in practice (operations return new instances).
- IPv4 and IPv6 are supported uniformly.
- Large-number operations return `numeric-string` when needed.

## Creating IP Instances

```php
use IPTools\IP;

$ipv4 = new IP('192.168.1.1');
$ipv6 = new IP('2001:db8::1');

$also = IP::parse('10.0.0.1');
```

Use explicit parse methods when your input source is non-standard:

- `IP::parse(int|string $ip)`
- `IP::parseBin(string $binIP)`
- `IP::parseHex(string $hexIP)`
- `IP::parseLong(int|string $longIP, string $version = IP::IP_V4)`
- `IP::parseInAddr(string $inAddr)`

`parseLong` is strict about range limits:

- IPv4: `0 .. 4294967295`
- IPv6: `0 .. 340282366920938463463374607431768211455`

## Working With Representations

```php
$ip = new IP('10.0.0.1');

echo $ip->version;         // ip4 / ip6
echo $ip->maxPrefixLength; // 32 / 128
echo $ip->octetsCount;     // 4 / 16
echo $ip->reversePointer;  // reverse DNS pointer

echo $ip->toBin();         // binary string
echo $ip->toHex();         // hex string
echo $ip->expanded();      // fully expanded textual format
echo $ip->toLong();        // numeric-string
```

## Classification and Address Intent

Classification helpers let you build routing, filtering, and safety logic.

```php
use IPTools\IPType;

$ip = new IP('127.0.0.1');

$types = $ip->types();        // IPType[]
$primary = $ip->primaryType();

var_dump($ip->is(IPType::LOOPBACK));
var_dump($ip->isLoopback());
var_dump($ip->isPrivate());
var_dump($ip->isGlobalRoutable());
```

Common checks:

- `isLoopback()`
- `isPrivate()`
- `isMulticast()`
- `isLinkLocal()`
- `isDocumentation()`
- `isReserved()`

## IPv4/IPv6 Transition Helpers

Useful when interoperating with dual-stack systems, tunneling, or translation layers.

```php
// IPv4-mapped IPv6
$mapped = IP::toIpv4Mapped(new IP('127.0.0.1'));
$v4 = IP::fromIpv4Mapped($mapped);

// 6to4 conversion
$sixToFour = IP::to6to4(new IP('10.0.0.1'));
$v4Again = IP::from6to4($sixToFour);

// NAT64 with default /96 prefix
$nat64 = IP::toNat64(new IP('8.8.8.8'));
$v4FromNat64 = IP::fromNat64($nat64);

var_dump($mapped->isIpv4Mapped());
var_dump($sixToFour->is6to4());
var_dump($nat64->isNat64());
```

NAT64 notes:

- Prefix must be IPv6 `/96`.
- Custom prefix is supported through `toNat64(..., $prefix)` and `fromNat64(..., $prefix)`.

## Arithmetic and Overflow Modes

Use arithmetic APIs for offset-based traversal, indexing, and distance calculations.

```php
use IPTools\OverflowMode;

$ip = new IP('192.168.0.1');

echo $ip->compareTo(new IP('192.168.0.2'));      // -1
echo $ip->distanceTo(new IP('192.168.0.11'));    // 10

echo $ip->addOffset(5);                          // 192.168.0.6
echo $ip->next();                                // 192.168.0.2
echo $ip->previous();                            // 192.168.0.0
echo $ip->shift(1);                              // bit shift right
echo $ip->shift(-1);                             // bit shift left

$max = new IP('255.255.255.255');
var_dump($max->addOffset(1, OverflowMode::NULL));
echo $max->addOffset(1, OverflowMode::WRAP);     // 0.0.0.0
echo $max->addOffset(1, OverflowMode::CLAMP);    // 255.255.255.255
```

Overflow strategies:

- `THROW` throws `OverflowException`
- `NULL` returns `null` on out-of-range results
- `WRAP` wraps modulo address-space size
- `CLAMP` clamps to nearest boundary

## Common Patterns

### Iterate safely without exceptions

```php
$cursor = new IP('10.0.0.1');
while ($cursor !== null) {
    // process
    $cursor = $cursor->next();
}
```

### Build relative offsets from signed user input

```php
$result = $ip->addOffset($delta, OverflowMode::NULL);
if ($result === null) {
    // outside address space
}
```

## Exceptions and Validation

Typical exceptions:

- `IPTools\Exception\IpException` for invalid address format or version mismatch
- `InvalidArgumentException` for invalid transition helper input
- `OverflowException` when overflow mode is `THROW`
