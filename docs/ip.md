# IP Guide

Back to index: [Documentation Index](README.md)

The `IPTools\IP` class is the core value object for individual IP addresses.

## Construction and Parsing

```php
use IPTools\IP;

$ip = new IP('192.168.1.1');
$ip = IP::parse('2001:db8::1');
```

Supported parse entry points:

- `IP::parse(int|string $ip)`
- `IP::parseBin(string $binIP)`
- `IP::parseHex(string $hexIP)`
- `IP::parseLong(int|string $longIP, string $version = IP::IP_V4)`
- `IP::parseInAddr(string $inAddr)` (binary in_addr bytes)

`parseLong` validates input bounds:

- IPv4 range: `0 .. 4294967295`
- IPv6 range: `0 .. 340282366920938463463374607431768211455`

## Representation APIs

```php
$ip = new IP('10.0.0.1');

echo $ip->version;            // ip4
echo $ip->maxPrefixLength;    // 32 or 128
echo $ip->octetsCount;        // 4 or 16
echo $ip->reversePointer;     // PTR name

echo $ip->toBin();            // bit string
echo $ip->toHex();            // hex string
echo $ip->expanded();         // expanded canonical format
echo $ip->toLong();           // numeric-string
```

## Type Classification

Use type classification to reason about routing and intent.

```php
use IPTools\IPType;

$ip = new IP('127.0.0.1');

$allTypes = $ip->types();      // IPType[]
$primary = $ip->primaryType(); // IPType

var_dump($ip->isLoopback());
var_dump($ip->isPrivate());
var_dump($ip->isMulticast());
var_dump($ip->isLinkLocal());
var_dump($ip->isDocumentation());
var_dump($ip->isReserved());
var_dump($ip->isGlobalRoutable());

var_dump($ip->is(IPType::LOOPBACK));
```

## IPv4/IPv6 Transition Helpers

```php
// IPv4-mapped
$mapped = IP::toIpv4Mapped(new IP('127.0.0.1'));   // ::ffff:127.0.0.1
$v4 = IP::fromIpv4Mapped($mapped);                 // 127.0.0.1

// 6to4
$sixToFour = IP::to6to4(new IP('10.0.0.1'));       // 2002:a00:1::
$v4 = IP::from6to4($sixToFour);

// NAT64 (default prefix 64:ff9b::/96)
$nat64 = IP::toNat64(new IP('8.8.8.8'));
$v4 = IP::fromNat64($nat64);

var_dump($nat64->isNat64());
```

NAT64 prefix must be IPv6 `/96`.

## Arithmetic APIs

Arithmetic operations are big-integer safe and support explicit overflow behavior.

```php
use IPTools\OverflowMode;

$ip = new IP('192.168.0.1');

echo $ip->compareTo(new IP('192.168.0.2')); // -1
echo $ip->distanceTo(new IP('192.168.0.11')); // 10

echo (string) $ip->addOffset(5);             // 192.168.0.6
echo (string) $ip->next();                   // +1
echo (string) $ip->previous();               // -1
echo (string) $ip->shift(1);                 // right shift 1
echo (string) $ip->shift(-1);                // left shift 1

$max = new IP('255.255.255.255');
var_dump($max->addOffset(1, OverflowMode::NULL));   // null
echo (string) $max->addOffset(1, OverflowMode::WRAP); // 0.0.0.0
echo (string) $max->addOffset(1, OverflowMode::CLAMP); // 255.255.255.255
```

`OverflowMode` values:

- `THROW` - throw `OverflowException`
- `NULL` - return `null`
- `WRAP` - modular wraparound
- `CLAMP` - clamp to min/max address

## Error Model

Typical exceptions:

- `IPTools\Exception\IpException` for invalid parse/format/state
- `InvalidArgumentException` for invalid conversion inputs in specific helpers
- `OverflowException` for overflow when mode is `THROW`
