# API Reference

Back to index: [Documentation Index](README.md)

This file summarizes the public API surface for the main package classes.

## IPTools\IP

Creation/parsing:

- `__construct(string $ip)`
- `static parse(int|string $ip): IP`
- `static parseBin(string $binIP): IP`
- `static parseHex(string $hexIP): IP`
- `static parseLong(int|string $longIP, string $version = IP::IP_V4): IP`
- `static parseInAddr(string $inAddr): IP`

Representations:

- `__toString(): string`
- `inAddr(): string`
- `toBin(): string`
- `toHex(): string`
- `expanded(): string`
- `toLong(): numeric-string`

Metadata:

- `getVersion(): string`
- `getMaxPrefixLength(): int`
- `getOctetsCount(): int`
- `getReversePointer(): string`

Type helpers:

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

Transition helpers:

- `isIpv4Mapped(): bool`
- `is6to4(): bool`
- `isNat64(string $prefix = '64:ff9b::/96'): bool`
- `static toIpv4Mapped(IP $ipv4): IP`
- `static fromIpv4Mapped(IP|string $ipv6): IP`
- `static to6to4(IP $ipv4): IP`
- `static from6to4(IP|string $ipv6): IP`
- `static toNat64(IP $ipv4, string $prefix = '64:ff9b::/96'): IP`
- `static fromNat64(IP|string $ipv6, string $prefix = '64:ff9b::/96'): IP`

Arithmetic:

- `compareTo(IP $other): int`
- `distanceTo(IP $other): numeric-string`
- `addOffset(int|string $delta, OverflowMode $mode = OverflowMode::THROW): ?IP`
- `next(int|string $steps = 1): ?IP`
- `previous(int|string $steps = 1): ?IP`
- `shift(int $bits, OverflowMode $mode = OverflowMode::THROW): ?IP`

## IPTools\Network

Core:

- `__construct(IP $ip, IP $netmask)`
- `__toString(): string`
- `static parse(string|IP|Network $data): Network`
- `static prefix2netmask(int|string $prefixLength, string $version): IP`
- `static netmask2prefix(IP $ip): int`
- `static summarize(array $networks): Network[]`

Getters/setters:

- `setIP(IP $ip): void`
- `setNetmask(IP $ip): void`
- `setPrefixLength(int $prefixLength): void`
- `getIP(): IP`
- `getNetmask(): IP`
- `getPrefixLength(): int`
- `getCIDR(): string`

Address helpers:

- `getNetwork(): IP`
- `getBroadcast(): IP`
- `getWildcard(): IP`
- `getFirstIP(): IP`
- `getLastIP(): IP`
- `networkAddress(): IP`
- `broadcastAddress(): IP`
- `firstHost(): IP`
- `lastHost(): IP`
- `usableHostCount(): int|string`
- `isPointToPoint(): bool`
- `containsIP(IP|string $ip): bool`
- `containsRange(Range|Network|IP|string $range): bool`
- `nextSubnet(): ?Network`
- `previousSubnet(): ?Network`

Operations:

- `exclude(string|IP|Network $exclude): Network[]`
- `moveTo(int|string $prefixLength): Network[]`
- `getHosts(): Range`
- `getBlockSize(): int|string`
- `getCountPrecise(): numeric-string`
- `count(): int`

Iteration (`Iterator`):

- `current(): IP`
- `key(): int`
- `next(): void`
- `rewind(): void`
- `valid(): bool`

## IPTools\Range

Core:

- `__construct(IP $firstIP, IP $lastIP)`
- `static parse(string $data): Range`
- `contains(IP|Network|Range $find): bool`

Getters/setters:

- `setFirstIP(IP $ip): void`
- `setLastIP(IP $ip): void`
- `getFirstIP(): IP`
- `getLastIP(): IP`

Network decomposition:

- `getNetworks(): Network[]`
- `iterateNetworks(): Generator<Network>`
- `getFirstNetwork(): Network`
- `getLastNetwork(): Network`
- `getNthNetwork(int $index): ?Network`
- `getSpanNetwork(): Network`

Indexing/counting:

- `addressAt(int|string $offset): ?IP`
- `addressAtOrFail(int|string $offset): IP`
- `getCountPrecise(): numeric-string`
- `count(): int`

Iteration (`Iterator`):

- `current(): IP`
- `key(): int`
- `next(): void`
- `rewind(): void`
- `valid(): bool`

## IPTools\RangeSet

Core:

- `__construct(iterable $ranges = [])`
- `static from(iterable $ranges): RangeSet`

Set operations:

- `union(...): RangeSet`
- `intersect(...): RangeSet`
- `subtract(...): RangeSet`

Queries:

- `contains(IP $ip): bool`
- `containsRange(Range|Network|IP|string $candidate): bool`
- `overlaps(Range|Network|IP|string $candidate): bool`

Exports:

- `toCidrs(): Network[]`
- `getRanges(): Range[]`
- `count(): int`

## IPTools\Parser

- `static ip(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress`
- `static range(string $input, int $flags = ParseFlags::DEFAULT): Range|Network`
- `static any(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress|Range|Network`

Related:

- `ParseFlags` constants
- `ParsedAddress` readonly DTO (`ip`, `port`, `zoneId`)

## Storage APIs

### IPTools\Storage\RangeStorageInterface

- `store(Network|Range $range, array $metadata = []): void`
- `delete(Network|Range $range): void`
- `contains(IP $ip): bool`
- `findContaining(IP $ip): iterable<array{range: Network|Range, metadata: array}>`
- `count(): int`

### IPTools\Storage\SqlRangeStorage

- `__construct(PDO $pdo, string $table = 'ip_ranges')`
- same interface methods as above

### IPTools\Storage\AddressCodec

- `static to16(IP $ip): string`
- `static from16(string $bin, int $version): IP`

### IPTools\Storage\LaravelRangeStorage

- `__construct(Illuminate\Database\Connection $connection, string $table = 'ip_ranges')`
- same interface methods as above

## Laravel Integration Classes

- `IPTools\IPToolsServiceProvider`
- `IPTools\Models\IpRange`

## Enums

### IPTools\IPType

- `UNSPECIFIED`
- `LOOPBACK`
- `BROADCAST`
- `MULTICAST`
- `LINK_LOCAL`
- `DOCUMENTATION`
- `BENCHMARKING`
- `CGNAT`
- `PRIVATE`
- `UNIQUE_LOCAL`
- `RESERVED`
- `GLOBAL`

### IPTools\OverflowMode

- `THROW`
- `NULL`
- `WRAP`
- `CLAMP`
