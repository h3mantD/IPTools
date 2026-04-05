# Networks

- [Documentation](README.md)

The `Network` class models CIDR blocks — an IP address paired with a prefix length (netmask). Use it when your logic is prefix-aware: routing tables, firewall rules, ACLs, subnet planning.

## Creating Networks

```php
use IPTools\Network;

$network = Network::parse('192.168.1.0/24');      // CIDR notation
$network = Network::parse('192.168.1.0 255.255.255.0'); // Dotted netmask
$network = Network::parse('192.168.1.1');          // Single host -> /32
$network = Network::parse('2001:db8::/64');        // IPv6
```

You can also construct directly with an IP and netmask:

```php
use IPTools\IP;

$network = new Network(new IP('192.168.1.0'), new IP('255.255.255.0'));
echo $network; // 192.168.1.0/24
```

> **Tip:** When you pass a bare IP without a prefix, it becomes a `/32` (IPv4) or `/128` (IPv6) — a single-host network.

## Network Properties

```php
$network = Network::parse('192.0.2.130/24');

echo $network;                     // 192.0.2.0/24
echo $network->getNetwork();       // 192.0.2.0     (network address)
echo $network->getBroadcast();     // 192.0.2.255   (broadcast address)
echo $network->getWildcard();      // 0.0.0.255
echo $network->getPrefixLength();  // 24
echo $network->getBlockSize();     // 256
echo $network->getFirstIP();       // 192.0.2.0
echo $network->getLastIP();        // 192.0.2.255
```

> **Tip:** `networkAddress()` and `broadcastAddress()` are aliases for `getNetwork()` and `getBroadcast()` — use whichever reads better in your code.

### Prefix / Netmask Conversion

```php
use IPTools\Enums\IPVersion;

$mask = Network::prefix2netmask(24, IPVersion::IPv4);
echo $mask; // 255.255.255.0

$prefix = Network::netmask2prefix(new IP('255.255.255.0'));
echo $prefix; // 24
```

## Host Boundaries

For most networks, the first usable host is `network + 1` and the last is `broadcast - 1`:

```php
$network = Network::parse('192.0.2.0/24');

echo $network->firstHost();       // 192.0.2.1
echo $network->lastHost();        // 192.0.2.254
echo $network->usableHostCount(); // 254
```

```php
$hosts = $network->getHosts();  // Returns a Range of usable hosts
echo $hosts->getFirstIP();     // 192.0.2.1
echo $hosts->getLastIP();      // 192.0.2.254
```

> **Note:** IPv4 `/31` networks (point-to-point links, per RFC 3021) and `/32` networks (single hosts) treat all addresses as usable — there's no separate network or broadcast address. IPv6 networks never reserve addresses for broadcast.

```php
$ptp = Network::parse('10.0.0.0/31');

$ptp->isPointToPoint();    // true
$ptp->usableHostCount();   // 2
$ptp->firstHost();         // 10.0.0.0
$ptp->lastHost();          // 10.0.0.1
```

## Containment Checks

```php
$network = Network::parse('10.0.0.0/24');

$network->containsIP('10.0.0.42');            // true
$network->containsIP('10.0.1.1');             // false
$network->containsRange('10.0.0.10-10.0.0.20'); // true
$network->containsRange(Network::parse('10.0.0.0/25')); // true
$network->containsRange('10.0.1.0/24');       // false
```

`containsRange()` accepts `Range`, `Network`, `IP`, or a string. Mixed IPv4/IPv6 comparisons always return `false`.

## Subnet Navigation

Step through consecutive subnets of the same size:

```php
$network = Network::parse('10.0.0.0/24');

echo $network->nextSubnet();     // 10.0.1.0/24
echo $network->previousSubnet(); // 9.255.255.0/24
```

Both return `null` when stepping would leave the address space:

```php
Network::parse('0.0.0.0/0')->previousSubnet();      // null
Network::parse('255.255.255.0/24')->nextSubnet();    // null
```

### Pagination Pattern

```php
$cursor = Network::parse('10.0.0.0/24');
while ($cursor !== null) {
    // process subnet
    $cursor = $cursor->nextSubnet();
}
```

## Excluding and Splitting

### Exclude a Subnet

Remove a subnet from a network, returning the remaining fragments:

```php
$remaining = Network::parse('192.0.2.0/28')->exclude('192.0.2.1/32');

foreach ($remaining as $network) {
    echo $network . PHP_EOL;
}
// 192.0.2.0/32
// 192.0.2.2/31
// 192.0.2.4/30
// 192.0.2.8/29
```

The excluded subnet must be fully within the source network, or an exception is thrown.

### Split Into Smaller Subnets

```php
$subnets = Network::parse('192.168.0.0/22')->moveTo(24);

foreach ($subnets as $subnet) {
    echo $subnet . PHP_EOL;
}
// 192.168.0.0/24
// 192.168.1.0/24
// 192.168.2.0/24
// 192.168.3.0/24
```

## Summarization

Collapse overlapping and adjacent networks into the minimal CIDR set:

```php
$summary = Network::summarize([
    '10.0.0.0/24',
    '10.0.1.0/24',
    '10.0.0.0/25',   // already contained in /24
]);

foreach ($summary as $network) {
    echo $network . PHP_EOL;
}
// 10.0.0.0/23
```

This is useful for cleaning up redundant route entries or firewall rules.

## Counting and Iteration

```php
$network = Network::parse('192.168.1.0/24');

count($network);               // 256
$network->getCountPrecise();   // '256' (as string)
$network->getBlockSize();      // 256
```

You can iterate over every IP in a network:

```php
$small = Network::parse('10.0.0.0/30');
foreach ($small as $ip) {
    echo $ip . PHP_EOL;
}
// 10.0.0.0
// 10.0.0.1
// 10.0.0.2
// 10.0.0.3
```

> **Warning:** Don't iterate over large networks. A `/16` has 65,536 addresses — that's fine. A `/64` has 18 quintillion — that will never finish. For large networks, use `firstHost()`/`lastHost()`, `moveTo()`, or `getCountPrecise()` instead.

### Large IPv6 Networks

```php
$big = Network::parse('2001:db8::/64');

echo $big->getCountPrecise(); // '18446744073709551616'
echo count($big);             // 9223372036854775807 (clamped to PHP_INT_MAX)
```

## Troubleshooting

### "Invalid prefix length"

The prefix must be between 0 and 32 (IPv4) or 0 and 128 (IPv6), and `moveTo()` requires a longer prefix than the current one.

### "Exclude subnet not within target network"

The network you're excluding must be fully contained within the source. Check with `containsRange()` first.

### "IP version is not same as Netmask version"

You can't mix an IPv4 address with an IPv6 netmask (or vice versa).

## What's Next?

- **[Ranges](ranges.md)** — For working with arbitrary start/end intervals (not just CIDR-aligned)
- **[Range Sets](range-sets.md)** — For merging, diffing, and intersecting collections of networks
- **[API Reference](api-reference.md)** — Full method listing for the `Network` class
