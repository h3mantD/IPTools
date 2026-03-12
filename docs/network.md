# Network Guide

Back to index: `docs/README.md`

`IPTools\Network` models CIDR blocks and provides subnet math, host helpers, iteration, and decomposition.

## Parsing and Construction

```php
use IPTools\Network;

$n1 = Network::parse('192.168.1.130/24');
$n2 = Network::parse('192.168.1.130 255.255.255.0');
$n3 = Network::parse('2001:db8::/64');
```

Helpers:

- `Network::prefix2netmask(int|string $prefixLength, string $version): IP`
- `Network::netmask2prefix(IP $ip): int`

## Core Properties and Accessors

- `getIP()`, `setIP()`
- `getNetmask()`, `setNetmask()`
- `getPrefixLength()`, `setPrefixLength()`
- `getCIDR()` / string cast
- `getNetwork()` / `networkAddress()`
- `getBroadcast()` / `broadcastAddress()`
- `getWildcard()`
- `getFirstIP()`, `getLastIP()`

## Host Helpers

```php
$n = Network::parse('192.0.2.130/24');

echo $n->firstHost();       // 192.0.2.1
echo $n->lastHost();        // 192.0.2.254
echo $n->usableHostCount(); // 254
```

Behavior rules:

- IPv4 `/31` and `/32` are treated as fully usable host sets.
- IPv6 has no broadcast reservation; all addresses are usable.
- `isPointToPoint()` returns:
  - `true` for IPv4 `/31`
  - `true` for IPv6 `/127`

## Containment Helpers

```php
$n = Network::parse('10.0.0.0/24');

var_dump($n->containsIP('10.0.0.42')); // true
var_dump($n->containsRange('10.0.0.10-10.0.0.20')); // true
var_dump($n->containsRange('10.0.1.0/24')); // false
```

`containsRange` accepts `Range|Network|IP|string`.

## Subnet Navigation

```php
$n = Network::parse('10.0.0.0/24');

echo $n->nextSubnet();     // 10.0.1.0/24
echo $n->previousSubnet(); // 9.255.255.0/24
```

Returns `null` when stepping would go outside the address space.

## Exclusion and Splitting

- `exclude(string|IP|Network $exclude): Network[]`
  - Excluded subnet must be fully within the source network.
- `moveTo(int|string $prefixLength): Network[]`
  - Splits a network into equal child subnets.

## Summarization

Use `Network::summarize(array $networks): Network[]` to collapse redundant and adjacent CIDRs.

```php
$summary = Network::summarize([
    '10.0.0.0/24',
    '10.0.1.0/24',
    '10.0.0.0/25',
]);
// -> 10.0.0.0/23
```

## Counting and Iteration

- `getBlockSize()` returns block size (`int` for IPv4, numeric-string for large IPv6)
- `getCountPrecise()` returns exact size as string
- `count()` returns `int` (clamped at `PHP_INT_MAX`)
- `Iterator` support allows iterating every address in the network

For very large networks, avoid materialized iteration.
