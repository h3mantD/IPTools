# IPTools

PHP Library for manipulating network addresses (IPv4 and IPv6).

This repository is a fork of [S1lentium/IPTools](https://github.com/S1lentium/IPTools).

[![CI](https://github.com/h3mantD/IPTools/actions/workflows/ci.yml/badge.svg)](https://github.com/h3mantD/IPTools/actions/workflows/ci.yml)
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

For full documentation, see `docs/README.md`.

Detailed guides:

- [IP Guide](docs/ip.md)
- [Parser Guide](docs/parser.md)
- [Network Guide](docs/network.md)
- [Range Guide](docs/range.md)
- [RangeSet Guide](docs/rangeset.md)
- [Storage Guide](docs/storage.md)
- [Laravel Integration Guide](docs/laravel.md)
- [API Reference](docs/api-reference.md)

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

### IP Type Classification

```php
$ip = new IP('127.0.0.1');

echo $ip->primaryType()->value; // loopback
var_dump($ip->isLoopback()); // true
var_dump($ip->isGlobalRoutable()); // false

$types = (new IP('233.252.0.1'))->types();
// MULTICAST + DOCUMENTATION (precedence keeps MULTICAST as primary)
```

### IP Arithmetic and Offsets

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

### IPv4 <-> IPv6 Conversions

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

### Flexible Parsing

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

### Database-Backed Range Lookup

This module is optional. If you do not need database-backed lookups, you can skip this entire section and continue using the in-memory IP/Network/Range features.

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\SqlRangeStorage;

$pdo = new PDO('sqlite::memory:');
$pdo->exec('CREATE TABLE ip_ranges (id INTEGER PRIMARY KEY AUTOINCREMENT, version INTEGER NOT NULL, start_bin BLOB NOT NULL, end_bin BLOB NOT NULL, metadata TEXT NULL)');

$storage = new SqlRangeStorage($pdo);
$storage->store(Network::parse('192.0.2.0/24'), ['source' => 'docs']);

var_dump($storage->contains(new IP('192.0.2.10'))); // true

foreach ($storage->findContaining(new IP('192.0.2.10')) as $match) {
    echo $match['range']->getFirstIP() . '-' . $match['range']->getLastIP() . PHP_EOL;
    var_dump($match['metadata']);
}
```

MySQL schema:

```sql
CREATE TABLE ip_ranges (
  id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
  version TINYINT NOT NULL,
  start_bin BINARY(16) NOT NULL,
  end_bin   BINARY(16) NOT NULL,
  metadata  JSON NULL,
  KEY idx_lookup (version, start_bin, end_bin)
) ENGINE=InnoDB;
```

PostgreSQL schema:

```sql
CREATE TABLE ip_ranges (
  id BIGSERIAL PRIMARY KEY,
  version SMALLINT NOT NULL,
  start_bin BYTEA NOT NULL,
  end_bin   BYTEA NOT NULL,
  metadata  JSONB NULL
);

CREATE INDEX idx_lookup ON ip_ranges (version, start_bin, end_bin);
```

#### Laravel Usage Notes

Laravel integration is optional. The package can run as plain PHP without Laravel, and DB-backed storage is only needed when you want persisted range lookup.

Dependency behavior:
- This package does not force-install Laravel components for all users.
- `illuminate/*` entries in this repository are used for package development/static analysis and to document optional integration.
- If your project is a normal Laravel app (`laravel/framework`), Illuminate dependencies are already present.
- If your project is not a Laravel app but you still want Laravel integration helpers, install them explicitly:

```bash
composer require illuminate/support illuminate/database illuminate/console
```

Laravel classes are provided under normal package namespaces (not under a dedicated `Laravel/` directory):
- `IPTools\IPToolsServiceProvider`
- `IPTools\Storage\LaravelRangeStorage`
- `IPTools\Models\IpRange`
- `IPTools\Console\InstallCommand` (`iptools:install`)

If your app uses package auto-discovery, the service provider is registered automatically.

1) Publish config (optional but recommended):

```bash
php artisan iptools:install

# Manual alternative
php artisan vendor:publish --tag=iptools-config
```

2) Publish migration (optional, only if you need DB-backed storage) and migrate:

```bash
php artisan vendor:publish --tag=iptools-migrations
php artisan migrate
```

If you skip migration and call storage APIs, the package throws a clear runtime exception that the table is missing.

3) Resolve storage from container (recommended):

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\RangeStorageInterface;

$storage = app(RangeStorageInterface::class);

$storage->store(Network::parse('10.0.0.0/24'), [
    'policy' => 'allow',
    'source' => 'admin',
]);

var_dump($storage->contains(new IP('10.0.0.42'))); // true
```

4) Direct adapter usage with Laravel connection:

```php
use IPTools\IP;
use IPTools\Network;
use IPTools\Storage\LaravelRangeStorage;

$storage = new LaravelRangeStorage(DB::connection(), 'ip_ranges');

$storage->store(Network::parse('10.0.0.0/24'), ['policy' => 'allow']);
var_dump($storage->contains(new IP('10.0.0.42'))); // true
```

5) Optional model publishing:

```bash
php artisan vendor:publish --tag=iptools-model
```

This publishes `app/Models/IpRange.php` so your app can customize Eloquent behavior while keeping storage APIs available.

Notes:
- `start_bin` and `end_bin` are stored as fixed-width 16-byte values for deterministic lexicographic comparison.
- IPv4 addresses are encoded as `12` zero bytes + `4` IPv4 bytes.
- Storage queries are inclusive at both boundaries (`start_bin <= addr <= end_bin`).

### Network Operations

```php
echo Network::parse('192.0.0.1 255.0.0.0')->CIDR; // 192.0.0.0/8
echo (string)Network::parse('192.0.0.1/8')->netmask; // 255.0.0.0
echo (string)Network::parse('192.0.0.1'); // 192.0.0.1/32
```

**Convenience helpers:**

```php
$network = Network::parse('192.0.2.130/24');

echo (string) $network->networkAddress(); // 192.0.2.0
echo (string) $network->broadcastAddress(); // 192.0.2.255
echo (string) $network->firstHost(); // 192.0.2.1
echo (string) $network->lastHost(); // 192.0.2.254
echo $network->usableHostCount(); // 254

var_dump($network->containsIP('192.0.2.42')); // true
var_dump($network->containsRange('192.0.2.10-192.0.2.20')); // true

echo (string) $network->nextSubnet(); // 192.0.3.0/24
echo (string) $network->previousSubnet(); // 192.0.1.0/24
```

Point-to-point behavior:

```php
$v4p2p = Network::parse('198.51.100.0/31');
var_dump($v4p2p->isPointToPoint()); // true
echo $v4p2p->usableHostCount(); // 2

$v6p2p = Network::parse('2001:db8::/127');
var_dump($v6p2p->isPointToPoint()); // true
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

### RangeSet Algebra

```php
use IPTools\IP;
use IPTools\RangeSet;

$set = new RangeSet([
    '10.0.0.0-10.0.0.10',
    '10.0.0.11-10.0.0.20',
]);

// Canonicalized automatically: adjacent ranges are merged
var_dump(count($set)); // 1

$other = new RangeSet(['10.0.0.5-10.0.0.15']);

$intersection = $set->intersect($other);
$difference = $set->subtract($other);
$union = $set->union('10.0.0.50-10.0.0.60');

var_dump($set->contains(new IP('10.0.0.8'))); // true
var_dump($set->containsRange('10.0.0.1-10.0.0.3')); // true
var_dump($set->overlaps('10.0.0.18-10.0.1.1')); // true

foreach ($difference->toCidrs() as $network) {
    echo (string) $network . PHP_EOL;
}
```

# License

The library is released under the [MIT](https://opensource.org/licenses/MIT).
