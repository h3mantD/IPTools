# IPTools

PHP Library for manipulating network addresses (IPv4 and IPv6).

This repository is a fork of [S1lentium/IPTools](https://github.com/S1lentium/IPTools).

[![Build Status](https://app.travis-ci.com/h3mantD/IPTools.svg?branch=master)](https://app.travis-ci.com/github/h3mantD/IPTools)
[![Code Climate](https://codeclimate.com/github/h3mantd/IPTools/badges/gpa.svg)](https://codeclimate.com/github/h3mantd/IPTools)

[![PHP 8.2](https://img.shields.io/badge/PHP-8.2-8892BF.svg)](http://php.net)
[![PHP 8.3](https://img.shields.io/badge/PHP-8.3-8892BF.svg)](http://php.net)
[![PHP 8.4](https://img.shields.io/badge/PHP-8.4-8892BF.svg)](http://php.net)

## Installation

Requires PHP >= 8.2.

Composer:
Run in command line:

```
composer require h3mantd/iptools
```

or put in composer.json:

```json
{
    "require": {
        "h3mantd/iptools": "*"
    }
}
```

## Usage

### IP Operations

```php
$ip = new IP('192.168.1.1');
echo $ip->version;// IPv4
```

```php
$ip = new IP('fc00::');
echo $ip->version; // IPv6
```

**Parsing IP from integer, binary and hex:**

```php
echo (string)IP::parse(2130706433); // 127.0.0.1
echo (string)IP::parse('0b11000000101010000000000100000001') // 192.168.1.1
echo (string)IP::parse('0x0a000001'); // 10.0.0.1
```

or:

```php
echo (string)IP::parseLong(2130706433); // 127.0.0.1
echo (string)IP::parseBin('11000000101010000000000100000001'); // 192.168.1.1
echo (string)IP::parseHex('0a000001'); // 10.0.0.1
```

**Converting IP to other formats:**

```php
echo IP::parse('192.168.1.1')->bin // 11000000101010000000000100000001
echo IP::parse('10.0.0.1')->hex // 0a000001
echo IP::parse('127.0.0.1')->long // 2130706433
echo IP::parse('2001:db8::1')->expanded() // 2001:0db8:0000:0000:0000:0000:0000:0001
```

#### Other public properties:

`maxPrefixLength`
The max number of bits in the address representation: 32 for IPv4, 128 for IPv6.

`octetsCount`
The count of octets in IP address: 4 for IPv4, 16 for IPv6

`reversePointer`
The name of the reverse DNS PTR for the address:

```php
echo IP::parse('192.0.2.5')->reversePointer; // 5.2.0.192.in-addr.arpa
echo IP::parse('2001:db8::567:89ab')->reversePointer; // b.a.9.8.7.6.5.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa
```

### IP Type Classification (RFC-0001)

```php
$ip = new IP('127.0.0.1');

echo $ip->primaryType()->value; // loopback
var_dump($ip->isLoopback()); // true
var_dump($ip->isGlobalRoutable()); // false

$types = (new IP('233.252.0.1'))->types();
// MULTICAST + DOCUMENTATION (precedence keeps MULTICAST as primary)
```

### IP Arithmetic and Offsets (RFC-0002)

```php
$ip = new IP('0.0.0.1');

echo (string) $ip->next(); // 0.0.0.2
echo (string) $ip->previous(); // 0.0.0.0
echo (string) $ip->addOffset(10); // 0.0.0.11
echo (string) $ip->shift(-1); // 0.0.0.2 (left shift by 1)

$distance = (new IP('10.0.0.1'))->distanceTo(new IP('10.0.0.10'));
echo $distance; // 9
```

```php
use IPTools\OverflowMode;

$max = new IP('255.255.255.255');

var_dump($max->next()); // null (boundary-safe convenience)
echo (string) $max->addOffset(1, OverflowMode::WRAP); // 0.0.0.0
echo (string) $max->addOffset(1, OverflowMode::CLAMP); // 255.255.255.255
```

### IPv4 <-> IPv6 Conversions (RFC-0003)

```php
// IPv4-mapped
$mapped = IP::toIpv4Mapped(new IP('127.0.0.1')); // ::ffff:127.0.0.1
echo (string) IP::fromIpv4Mapped($mapped); // 127.0.0.1

// 6to4
$sixToFour = IP::to6to4(new IP('10.0.0.1')); // 2002:a00:1::
echo (string) IP::from6to4($sixToFour); // 10.0.0.1

// NAT64 /96 (default 64:ff9b::/96)
$nat64 = IP::toNat64(new IP('8.8.8.8')); // 64:ff9b::808:808
echo (string) IP::fromNat64($nat64); // 8.8.8.8
```

### Flexible Parsing (RFC-0004)

```php
use IPTools\ParseFlags;
use IPTools\Parser;

$parsed = Parser::ip('[2001:db8::1]:443');
echo (string) $parsed->ip; // 2001:db8::1
echo $parsed->port; // 443

$zoned = Parser::ip('fe80::1%eth0');
echo $zoned->zoneId; // eth0

echo (string) Parser::ip('0x0a000001')->ip; // 10.0.0.1
echo (string) Parser::range('192.168.*.*'); // 192.168.0.0/16

Parser::ip('1.2.3.4:80', ParseFlags::STRICT); // throws in strict mode
```

### Network Operations

```php
echo Network::parse('192.0.0.1 255.0.0.0')->CIDR; // 192.0.0.0/8
echo (string)Network::parse('192.0.0.1/8')->netmask; // 255.0.0.0
echo (string)Network::parse('192.0.0.1'); // 192.0.0.1/32
```

**Exclude IP from Network:**

```php
$excluded = Network::parse('192.0.0.0/8')->exclude(new IP('192.168.1.1'));
foreach($excluded as $network) {
	echo (string)$network . '<br>';
}
```

    192.0.0.0/9
    192.128.0.0/11
    192.160.0.0/13
    192.168.0.0/24
    192.168.1.0/32
    192.168.1.2/31
    ...
    192.192.0.0/10

**Exclude Subnet from Network:**

```php
$excluded = Network::parse('192.0.0.0/8')->exclude(new Network('192.168.1.0/24'));
foreach($excluded as $network) {
	echo (string)$network . '<br>';
}
```

    192.0.0.0/9
    192.128.0.0/11
    192.160.0.0/13
    192.168.0.0/24
    192.168.2.0/23
    ...
    192.192.0.0/10

**Split network into equal subnets**

```php
$networks = Network::parse('192.168.0.0/22')->moveTo('24');
foreach ($networks as $network) {
	echo (string)$network . '<br>';
}
```

    192.168.0.0/24
    192.168.1.0/24
    192.168.2.0/24
    192.168.3.0/24

**Iterate over Network IP adresses:**

```php
$network = Network::parse('192.168.1.0/24');
foreach($network as $ip) {
	echo (string)$ip . '<br>';
}
```

    192.168.1.0
    ...
    192.168.1.255

**Get Network hosts adresses as Range:**

```php
$hosts = Network::parse('192.168.1.0/24')->hosts // Range(192.168.1.1, 192.168.1.254);
foreach($hosts as $ip) {
	echo (string)$ip . '<br>';
}
```

    192.168.1.1
    ...
    192.168.1.254

**Count Network IP adresses**

```php
echo count(Network::parse('192.168.1.0/24')) // 254
```

**Count very large Networks precisely (IPv6):**

```php
echo Network::parse('2001:db8::/64')->getCountPrecise(); // 18446744073709551616
```

**Summarize adjacent/redundant networks:**

```php
$summary = Network::summarize([
    '10.0.0.0/24',
    '10.0.1.0/24',
    '10.0.0.0/25',
]);

foreach ($summary as $network) {
    echo (string) $network . PHP_EOL;
}
// 10.0.0.0/23
```

### Range Operations

**Define the range in different formats:**

```php
$range = new Range(new IP('192.168.1.0'), new IP('192.168.1.255'));
$range = Range::parse('192.168.1.0-192.168.1.255');
$range = Range::parse('192.168.1.*');
$range = Range::parse('192.168.1.0/24');
```

**Check if IP is within Range:**

```php
echo Range::parse('192.168.1.1-192.168.1.254')->contains(new IP('192.168.1.5')); // true
echo Range::parse('::1-::ffff')->contains(new IP('::1234')); // true
```

**Iterate over Range IP adresses:**

```php
$range = Range::parse('192.168.1.1-192.168.1.254');
foreach($range as $ip) {
	echo (string)$ip . '<br>';
}
```

    192.168.1.1
    ...
    192.168.1.254

**Get Networks that fit into a specified range of IP Adresses:**

```php
$networks = Range::parse('192.168.1.1-192.168.1.254')->getNetworks();

foreach($networks as $network) {
	echo (string)$network . '<br>';
}
```

    192.168.1.1/32
    192.168.1.2/31
    192.168.1.4/30
    192.168.1.8/29
    192.168.1.16/28
    192.168.1.32/27
    192.168.1.64/26
    192.168.1.128/26
    192.168.1.192/27
    192.168.1.224/28
    192.168.1.240/29
    192.168.1.248/30
    192.168.1.252/31
    192.168.1.254/32

**Count IP adresses in Range**

```php
echo count(Range::parse('192.168.1.1-192.168.1.254')) // 254
```

**Count very large Ranges precisely (IPv6):**

```php
echo Range::parse('2001:db8::/64')->getCountPrecise(); // 18446744073709551616
```

**Lazy network decomposition helpers:**

```php
$range = Range::parse('49.12.11.10-49.12.11.35');

echo (string) $range->getFirstNetwork(); // 49.12.11.10/31
echo (string) $range->getLastNetwork();  // 49.12.11.32/30
echo (string) $range->getNthNetwork(2);  // 49.12.11.16/28

foreach ($range->iterateNetworks() as $network) {
    echo (string) $network . PHP_EOL;
}
```

# License

The library is released under the [MIT](https://opensource.org/licenses/MIT).
