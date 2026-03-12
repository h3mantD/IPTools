# Network Guide

Back to index: [Documentation Index](README.md)

`IPTools\Network` models CIDR blocks and provides subnet math, host helpers, iteration, and decomposition.

Use this class when your logic is prefix-aware (routing tables, firewall rules, ACLs, subnet planning).

## Parsing and Construction

```php
use IPTools\Network;

$n1 = Network::parse('192.168.1.130/24');
$n2 = Network::parse('192.168.1.130 255.255.255.0');
$n3 = Network::parse('2001:db8::/64');
```

Accepted parse forms:

- CIDR: `192.168.1.130/24`
- IP + netmask: `192.168.1.130 255.255.255.0`
- Host-only: `192.168.1.130` (becomes `/32` or `/128`)

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

`__toString()` returns CIDR (`getCIDR()`).

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

These semantics align with modern operational usage where point-to-point prefixes may have both endpoints usable.

## Containment Helpers

```php
$n = Network::parse('10.0.0.0/24');

var_dump($n->containsIP('10.0.0.42')); // true
var_dump($n->containsRange('10.0.0.10-10.0.0.20')); // true
var_dump($n->containsRange('10.0.1.0/24')); // false
```

`containsRange` accepts `Range|Network|IP|string`.

Containment checks are version-aware. Mixed-version candidates return `false`.

## Subnet Navigation

```php
$n = Network::parse('10.0.0.0/24');

echo $n->nextSubnet();     // 10.0.1.0/24
echo $n->previousSubnet(); // 9.255.255.0/24
```

Returns `null` when stepping would go outside the address space.

This makes subnet traversal easy in pagination-style workflows:

```php
$cursor = Network::parse('10.0.0.0/24');
while ($cursor !== null) {
    // process subnet
    $cursor = $cursor->nextSubnet();
}
```

## Exclusion and Splitting

- `exclude(string|IP|Network $exclude): Network[]`
  - Excluded subnet must be fully within the source network.
- `moveTo(int|string $prefixLength): Network[]`
  - Splits a network into equal child subnets.

`exclude(...)` requires that the excluded target is fully contained in the source network.

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

Prefer:

- direct boundary access (`firstHost`, `lastHost`)
- decomposition (`moveTo`, `summarize`)
- numeric counts (`getCountPrecise`) for reporting

## Practical Examples

### Build host boundaries for provisioning

```php
$n = Network::parse('172.16.10.0/24');
$first = $n->firstHost();
$last = $n->lastHost();
```

### Check whether a rule contains another rule

```php
$container = Network::parse('10.0.0.0/16');
var_dump($container->containsRange('10.0.10.0/24')); // true
```

### Collapse redundant route entries

```php
$routes = Network::summarize([
    '10.0.0.0/24',
    '10.0.1.0/24',
    '10.0.0.0/25',
]);
```
