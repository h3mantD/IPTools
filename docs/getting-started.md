# Getting Started

- [Documentation](README.md)

## Installation

IPTools requires PHP 8.2+ and the `bcmath` extension (needed for IPv6 arithmetic).

```bash
composer require h3mantd/iptools
```

That's it. No configuration needed for core features.

## Your First IP Address

```php
use IPTools\IP;

$ip = new IP('192.168.1.1');

echo $ip;                    // 192.168.1.1
echo $ip->getVersion()->value; // IPv4
echo $ip->getMaxPrefixLength(); // 32
```

IPTools also handles IPv6 transparently:

```php
$ipv6 = new IP('2001:db8::1');

echo $ipv6;                  // 2001:db8::1
echo $ipv6->getVersion()->value; // IPv6
echo $ipv6->getMaxPrefixLength(); // 128
```

## Parsing From Different Formats

Real-world input doesn't always come as clean dotted-quad strings. The `parse()` method handles integers, hex, and binary:

```php
echo IP::parse(2130706433);         // 127.0.0.1  (from integer)
echo IP::parse('0x7f000001');       // 127.0.0.1  (from hex)
echo IP::parse('0b01111111000000000000000000000001'); // 127.0.0.1 (from binary)
```

## Checking Address Types

One of the most common tasks is determining what kind of address you're dealing with:

```php
$ip = new IP('10.0.0.1');

$ip->isPrivate();       // true — RFC 1918 private space
$ip->isLoopback();      // false
$ip->isGlobalRoutable(); // false
```

```php
$ip = new IP('8.8.8.8');

$ip->isGlobalRoutable(); // true — Google's public DNS
$ip->isPrivate();        // false
```

This works for IPv6 too:

```php
(new IP('::1'))->isLoopback();       // true
(new IP('fe80::1'))->isLinkLocal();  // true
(new IP('2001:db8::1'))->isDocumentation(); // true
```

## Building a Simple Allow-List

Here's a practical example — checking if an incoming IP is within an allowed network:

```php
use IPTools\IP;
use IPTools\Network;

$allowedNetwork = Network::parse('10.0.0.0/24');
$clientIP = new IP('10.0.0.42');

if ($allowedNetwork->containsIP($clientIP)) {
    echo "Access granted";
} else {
    echo "Access denied";
}
```

You can also check against a range:

```php
use IPTools\Range;

$range = Range::parse('192.168.1.100-192.168.1.200');

if ($range->contains(new IP('192.168.1.150'))) {
    echo "IP is in the DHCP range";
}
```

## Handling User Input Safely

When parsing IP addresses from forms, logs, or API input, use the `Parser` class. It handles ports, zone IDs, and gives you clean error handling:

```php
use IPTools\Parser;

try {
    $parsed = Parser::ip($userInput);
    $ip = $parsed->ip;       // clean IP object
    $port = $parsed->port;   // int or null
} catch (\IPTools\Exception\IpException $e) {
    echo "Invalid IP: {$e->getMessage()}";
}
```

The parser handles formats like `192.168.1.1:8080`, `[::1]:443`, and `fe80::1%eth0` out of the box.

## What's Next?

You've seen the basics. Here's where to go from here:

- **[IP Addresses](ip-addresses.md)** — Deep dive into parsing, arithmetic, classification, and IPv4/IPv6 conversions
- **[Networks](networks.md)** — CIDR subnets, host boundaries, exclusion, and splitting
- **[Comparison Guide](comparison.md)** — Not sure whether to use `Network`, `Range`, or `RangeSet`? This guide helps you choose
