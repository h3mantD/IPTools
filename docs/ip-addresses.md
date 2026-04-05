# IP Addresses

- [Documentation](README.md)

The `IP` class is the foundation of IPTools. Every other class — `Network`, `Range`, `RangeSet` — is built around it. An `IP` instance represents a single IPv4 or IPv6 address as an immutable value object.

## Creating IP Instances

The simplest way to create an IP is with the constructor:

```php
use IPTools\IP;

$ipv4 = new IP('192.168.1.1');   // IPv4
$ipv6 = new IP('2001:db8::1');   // IPv6
```

For non-standard input formats, use the `parse()` factory:

```php
echo IP::parse(2130706433);       // 127.0.0.1  (from integer)
echo IP::parse('0x7f000001');     // 127.0.0.1  (from hex)
echo IP::parse('0b01111111000000000000000000000001'); // 127.0.0.1 (from binary)
```

There are also dedicated parsers when you know the format:

```php
use IPTools\Enums\IPVersion;

$ip = IP::parseLong(2130706433);                  // 127.0.0.1
$ip = IP::parseLong('1', IPVersion::IPv6);        // ::1
$ip = IP::parseHex('7f000001');                    // 127.0.0.1
$ip = IP::parseBin('01111111000000000000000000000001'); // 127.0.0.1
```

> **Note:** `parseLong()` accepts both `int` and `string`. For IPv6, you must pass the version parameter since IPv6 values can have up to 39 digits.

## Representations

Every IP can be converted to multiple formats:

```php
$ip = new IP('10.0.0.1');

echo $ip;                   // 10.0.0.1    (string cast)
echo $ip->toBin();          // 00001010000000000000000000000001
echo $ip->toHex();          // 0a000001
echo $ip->toLong();         // 167772161
echo $ip->expanded();       // 10.0.0.1    (same for IPv4)
echo $ip->getReversePointer(); // 1.0.0.10.in-addr.arpa
```

IPv6 expanded form shows the full address:

```php
$ip = new IP('2001:db8::1');

echo $ip->expanded();  // 2001:0db8:0000:0000:0000:0000:0000:0001
```

> **Tip:** All these conversions are also available as magic properties: `$ip->bin`, `$ip->hex`, `$ip->long`, `$ip->expanded`, `$ip->reversePointer`. They call the same methods under the hood.

## Version Detection

```php
$ip = new IP('192.168.1.1');

echo $ip->getVersion();        // IPVersion::IPv4
echo $ip->getMaxPrefixLength(); // 32
echo $ip->getOctetsCount();    // 4
```

```php
$ip = new IP('2001:db8::1');

echo $ip->getVersion();        // IPVersion::IPv6
echo $ip->getMaxPrefixLength(); // 128
echo $ip->getOctetsCount();    // 16
```

`getVersion()` returns an `IPVersion` enum, which you can compare directly:

```php
use IPTools\Enums\IPVersion;

if ($ip->getVersion() === IPVersion::IPv4) {
    // IPv4-specific logic
}
```

## Classifying Addresses

IPTools classifies addresses according to IANA registries. Every IP has one or more types:

```php
use IPTools\Enums\IPType;

$ip = new IP('127.0.0.1');

$ip->isLoopback();       // true
$ip->primaryType();      // IPType::LOOPBACK
$ip->types();            // [IPType::LOOPBACK]
$ip->is(IPType::LOOPBACK); // true
```

Here are the most commonly used checks:

| Method | Example IPs |
|--------|-------------|
| `isLoopback()` | `127.0.0.1`, `::1` |
| `isPrivate()` | `10.x`, `172.16.x`, `192.168.x`, `fc00::` |
| `isMulticast()` | `224.0.0.1`, `ff02::1` |
| `isLinkLocal()` | `169.254.x.x`, `fe80::` |
| `isDocumentation()` | `192.0.2.x`, `2001:db8::` |
| `isReserved()` | `240.0.0.1` |
| `isGlobalRoutable()` | `8.8.8.8`, `2607:f8b0::` |

### Practical Example: Filtering Private IPs

```php
$addresses = ['8.8.8.8', '10.0.0.1', '192.168.1.1', '1.1.1.1'];

$public = array_filter($addresses, function (string $addr) {
    return (new IP($addr))->isGlobalRoutable();
});

// ['8.8.8.8', '1.1.1.1']
```

> **Note:** An address can belong to multiple types. For example, `233.252.0.1` is both `MULTICAST` and `DOCUMENTATION`. The `primaryType()` method returns the highest-precedence type based on IANA ordering.

## IPv4/IPv6 Transition Helpers

When working with dual-stack systems, tunneling, or translation layers, these conversion methods bridge IPv4 and IPv6:

### IPv4-Mapped (RFC 4291)

```php
$mapped = IP::toIpv4Mapped(new IP('192.168.1.1'));
echo $mapped;              // ::ffff:192.168.1.1
echo $mapped->isIpv4Mapped(); // true

$v4 = IP::fromIpv4Mapped($mapped);
echo $v4;                  // 192.168.1.1
```

### 6to4 (RFC 3056)

```php
$sixToFour = IP::to6to4(new IP('10.0.0.1'));
echo $sixToFour;           // 2002:a00:1::
echo $sixToFour->is6to4(); // true

$v4 = IP::from6to4($sixToFour);
echo $v4;                  // 10.0.0.1
```

### NAT64 (RFC 6052)

```php
$nat64 = IP::toNat64(new IP('8.8.8.8'));
echo $nat64;               // 64:ff9b::808:808
echo $nat64->isNat64();    // true

$v4 = IP::fromNat64($nat64);
echo $v4;                  // 8.8.8.8
```

> **Tip:** NAT64 uses the well-known prefix `64:ff9b::/96` by default. You can pass a custom `/96` prefix as the second argument: `IP::toNat64($ip, 'your:prefix::/96')`.

## Arithmetic and Offsets

IP addresses support arithmetic operations for traversal, indexing, and distance calculations.

### Basic Navigation

```php
$ip = new IP('192.168.0.1');

echo $ip->next();           // 192.168.0.2
echo $ip->next(10);         // 192.168.0.11
echo $ip->previous();       // 192.168.0.0
echo $ip->addOffset(100);   // 192.168.0.101
echo $ip->addOffset(-1);    // 192.168.0.0
```

At the address-space boundary, `next()` and `previous()` return `null` instead of throwing:

```php
$max = new IP('255.255.255.255');
$max->next();   // null

$min = new IP('0.0.0.0');
$min->previous(); // null
```

### Comparison and Distance

```php
$a = new IP('10.0.0.1');
$b = new IP('10.0.0.10');

$a->compareTo($b); // -1  (a < b)
$a->compareTo($a); //  0  (equal)
$b->compareTo($a); //  1  (b > a)

$a->distanceTo($b); // '9'  (signed distance as string)
```

### Bit Shifting

```php
echo (new IP('0.0.0.8'))->shift(1);  // 0.0.0.4  (right shift)
echo (new IP('0.0.0.1'))->shift(-1); // 0.0.0.2  (left shift)
```

### Overflow Modes

When arithmetic would go past the address-space boundary, you control the behavior with `OverflowMode`:

```php
use IPTools\Enums\OverflowMode;

$max = new IP('255.255.255.255');

$max->addOffset(1, OverflowMode::THROW); // throws OverflowException (default)
$max->addOffset(1, OverflowMode::NULL);  // null
$max->addOffset(1, OverflowMode::WRAP);  // 0.0.0.0 (wraps around)
$max->addOffset(1, OverflowMode::CLAMP); // 255.255.255.255 (stays at max)
```

> **Warning:** IPv6 values can be up to 39 digits long, far exceeding `PHP_INT_MAX`. All arithmetic is done with `bcmath` internally, and results are returned as `numeric-string` when needed. Use `toLong()` for exact values — never cast to `int` for IPv6.

## Common Patterns

### Safe Iteration Without Exceptions

```php
$cursor = new IP('10.0.0.1');
while ($cursor !== null) {
    // process $cursor
    $cursor = $cursor->next();
}
```

### Boundary-Safe Offset From User Input

```php
$result = $ip->addOffset($userDelta, OverflowMode::NULL);
if ($result === null) {
    // offset would exceed address space
}
```

## Troubleshooting

### "Invalid IP address format"

The constructor uses PHP's `inet_pton()` under the hood. This error means the string isn't a valid IPv4 or IPv6 address. Common causes:

- Typos: `192.168.1.256` (octet > 255)
- Port included: `192.168.1.1:80` (use `Parser::ip()` instead)
- CIDR notation: `10.0.0.0/24` (use `Network::parse()` instead)

### "Cannot compare IPs of different versions"

`compareTo()` and `distanceTo()` require both IPs to be the same version. Check with `getVersion()` before comparing.

### "Long IP address is out of range"

`parseLong()` validates the value is within bounds for the specified version. IPv4 max is `4294967295`, IPv6 max is `340282366920938463463374607431768211455`.

## What's Next?

- **[Networks](networks.md)** — Work with CIDR subnets and subnet math
- **[Parsing](parsing.md)** — Parse IPs with ports, zone IDs, and other extras from user input
- **[API Reference](api-reference.md)** — Full method listing for the `IP` class
