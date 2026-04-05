# API Reference

- [Documentation](README.md)

Quick lookup for every public method. For conceptual guidance and examples, see the guide pages linked in each section header.

## IPTools\IP

> Guide: [IP Addresses](ip-addresses.md)

**Creation and parsing:**

- `__construct(string $ip)`
- `static parse(int|string $ip): IP`
- `static parseBin(string $binIP): IP`
- `static parseHex(string $hexIP): IP`
- `static parseLong(int|string $longIP, IPVersion|string $version = IPVersion::IPv4): IP`
- `static parseInAddr(string $inAddr): IP`

**Representations:**

- `__toString(): string`
- `inAddr(): string` — raw packed binary (advanced use)
- `toBin(): string`
- `toHex(): string`
- `expanded(): string`
- `toLong(): numeric-string`

**Version and metadata:**

- `getVersion(): IPVersion`
- `getMaxPrefixLength(): int`
- `getOctetsCount(): int`
- `getReversePointer(): string`

**Type classification:**

- `types(): IPType[]`
- `primaryType(): IPType`
- `is(IPType $type): bool`
- `isGlobalRoutable(): bool`
- `isPrivate(): bool`
- `isLoopback(): bool`
- `isMulticast(): bool`
- `isLinkLocal(): bool`
- `isDocumentation(): bool`
- `isReserved(): bool`

**IPv4/IPv6 transition helpers:**

- `isIpv4Mapped(): bool`
- `is6to4(): bool`
- `isNat64(string $prefix = '64:ff9b::/96'): bool`
- `static toIpv4Mapped(IP $ipv4): IP`
- `static fromIpv4Mapped(IP|string $ipv6): IP`
- `static to6to4(IP $ipv4): IP`
- `static from6to4(IP|string $ipv6): IP`
- `static toNat64(IP $ipv4, string $prefix = '64:ff9b::/96'): IP`
- `static fromNat64(IP|string $ipv6, string $prefix = '64:ff9b::/96'): IP`

**Arithmetic:**

- `compareTo(IP $other): int` — returns -1, 0, or 1
- `distanceTo(IP $other): numeric-string` — signed distance
- `addOffset(int|string $delta, OverflowMode $mode = OverflowMode::THROW): ?IP`
- `next(int|string $steps = 1): ?IP`
- `previous(int|string $steps = 1): ?IP`
- `shift(int $bits, OverflowMode $mode = OverflowMode::THROW): ?IP`

## IPTools\Network

> Guide: [Networks](networks.md)

**Creation:**

- `__construct(IP $ip, IP $netmask)`
- `__toString(): string` — returns CIDR notation
- `static parse(string|IP|Network $data): Network`
- `static prefix2netmask(int|string $prefixLength, IPVersion|string $version): IP`
- `static netmask2prefix(IP $ip): int`
- `static summarize(array $networks): Network[]`

**Properties:**

- `setIP(IP $ip): void`
- `setNetmask(IP $ip): void`
- `setPrefixLength(int $prefixLength): void`
- `getIP(): IP`
- `getNetmask(): IP`
- `getPrefixLength(): int`
- `getCIDR(): string`

**Address helpers:**

- `getNetwork(): IP` / `networkAddress(): IP`
- `getBroadcast(): IP` / `broadcastAddress(): IP`
- `getWildcard(): IP`
- `getFirstIP(): IP`
- `getLastIP(): IP`
- `firstHost(): IP`
- `lastHost(): IP`
- `usableHostCount(): int|string`
- `isPointToPoint(): bool`

**Containment and navigation:**

- `containsIP(IP|string $ip): bool`
- `containsRange(Range|Network|IP|string $range): bool`
- `nextSubnet(): ?Network`
- `previousSubnet(): ?Network`

**Operations:**

- `exclude(string|IP|Network $exclude): Network[]`
- `moveTo(int|string $prefixLength): Network[]`
- `getHosts(): Range`
- `getBlockSize(): int|string`
- `getCountPrecise(): numeric-string`
- `count(): int`

**Iterator:** `current(): IP`, `key(): int`, `next(): void`, `rewind(): void`, `valid(): bool`

## IPTools\Range

> Guide: [Ranges](ranges.md)

**Creation:**

- `__construct(IP $firstIP, IP $lastIP)`
- `static parse(string $data): Range`

**Endpoints:**

- `setFirstIP(IP $ip): void`
- `setLastIP(IP $ip): void`
- `getFirstIP(): IP`
- `getLastIP(): IP`

**Containment:**

- `contains(IP|Network|Range $find): bool`

**Network decomposition:**

- `getNetworks(): Network[]`
- `iterateNetworks(): Generator<Network>`
- `getFirstNetwork(): Network`
- `getLastNetwork(): Network`
- `getNthNetwork(int $index): ?Network`
- `getSpanNetwork(): Network`

**Indexing and counting:**

- `addressAt(int|string $offset): ?IP`
- `addressAtOrFail(int|string $offset): IP`
- `getCountPrecise(): numeric-string`
- `count(): int`

**Iterator:** `current(): IP`, `key(): int`, `next(): void`, `rewind(): void`, `valid(): bool`

## IPTools\RangeSet

> Guide: [Range Sets](range-sets.md)

**Creation:**

- `__construct(iterable $ranges = [])`
- `static from(iterable $ranges): RangeSet`

**Set operations** (all accept `RangeSet|iterable|Range|Network|IP|string`):

- `union(...): RangeSet`
- `intersect(...): RangeSet`
- `subtract(...): RangeSet`

**Queries:**

- `contains(IP $ip): bool`
- `containsRange(Range|Network|IP|string $candidate): bool`
- `overlaps(Range|Network|IP|string $candidate): bool`

**Exports:**

- `toCidrs(): Network[]`
- `getRanges(): Range[]`
- `count(): int`

## IPTools\Parser

> Guide: [Parsing](parsing.md)

- `static ip(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress`
- `static range(string $input, int $flags = ParseFlags::DEFAULT): Range|Network`
- `static any(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress|Range|Network`

## IPTools\ParseFlags

> Guide: [Parsing](parsing.md)

| Constant | Value | Description |
|----------|-------|-------------|
| `STRICT` | `0` | Accept only standard IP notation |
| `ALLOW_PORT` | `1` | Accept port suffix |
| `ALLOW_ZONE_ID` | `2` | Accept IPv6 zone identifiers |
| `ALLOW_NON_DECIMAL_IPV4` | `4` | Accept hex/binary/octal IPv4 |
| `ALLOW_NON_QUAD_IPV4` | `8` | Accept 2-part IPv4 shorthand |
| `ALLOW_WILDCARDS` | `16` | Accept `*` octets |
| `DEFAULT` | `23` | Port + Zone + NonDecimal + Wildcards |

## IPTools\ParsedAddress

> Guide: [Parsing](parsing.md)

```php
final readonly class ParsedAddress {
    public IP $ip;
    public ?int $port;
    public ?string $zoneId;
}
```

## Storage

> Guide: [Database Storage](database-storage.md) | [Laravel Integration](laravel.md)

### IPTools\Storage\RangeStorageInterface

- `store(Network|Range $range, array $metadata = []): void`
- `delete(Network|Range $range): void`
- `contains(IP $ip): bool`
- `findContaining(IP $ip): iterable<array{range: Range, metadata: array}>`
- `count(): int`

### IPTools\Storage\SqlRangeStorage

- `__construct(PDO $pdo, string $table = 'ip_ranges')`
- Implements `RangeStorageInterface`

### IPTools\Storage\LaravelRangeStorage

- `__construct(Connection $connection, string $table = 'ip_ranges')`
- Implements `RangeStorageInterface`

### IPTools\Storage\AddressCodec

- `static to16(IP $ip): string` — encode to 16-byte binary
- `static from16(string $bin, int $version): IP` — decode from 16-byte binary

## Enums

### IPTools\Enums\IPVersion

| Case | Value | `maxPrefixLength()` | `octets()` | `maxLong()` |
|------|-------|--------------------:|----------:|-------------|
| `IPv4` | `'IPv4'` | 32 | 4 | `'4294967295'` |
| `IPv6` | `'IPv6'` | 128 | 16 | `'340282366920938463463374607431768211455'` |

- `toInt(): int` — returns `4` or `6`
- `static resolve(self|string $version): self` — resolve from string for backward compatibility

### IPTools\Enums\IPType

`UNSPECIFIED`, `LOOPBACK`, `BROADCAST`, `MULTICAST`, `LINK_LOCAL`, `DOCUMENTATION`, `BENCHMARKING`, `CGNAT`, `PRIVATE`, `UNIQUE_LOCAL`, `RESERVED`, `GLOBAL`

### IPTools\Enums\OverflowMode

`THROW`, `NULL`, `WRAP`, `CLAMP`

## Laravel Classes

- `IPTools\IPToolsServiceProvider` — auto-discovered service provider
- `IPTools\Models\IpRange` — optional Eloquent model
- `IPTools\Console\InstallCommand` — `php artisan iptools:install`
