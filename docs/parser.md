# Parser Guide

Back to index: [Documentation Index](README.md)

`IPTools\Parser` provides controlled parsing for addresses, ranges, ports, zones, and wildcards.

It is useful when your input format is user-facing or comes from logs, forms, or network metadata.

## Main Entry Points

- `Parser::ip(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress`
- `Parser::range(string $input, int $flags = ParseFlags::DEFAULT): Range|Network`
- `Parser::any(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress|Range|Network`

When to use which:

- `ip()` when expecting a single endpoint
- `range()` when expecting CIDR/range/wildcard input
- `any()` when you want a smart parser and then branch on return type

## ParsedAddress

`ParsedAddress` is a readonly wrapper:

```php
final readonly class ParsedAddress {
    public IP $ip;
    public ?int $port;
    public ?string $zoneId;
}
```

This object preserves extra context (`port`, `zoneId`) while still giving a canonical `IP` instance.

## Parse Flags

Use `IPTools\ParseFlags` as bit flags.

- `STRICT` - disables optional parsing features
- `ALLOW_PORT`
- `ALLOW_ZONE_ID`
- `ALLOW_NON_DECIMAL_IPV4` (`0x`, `0b`, `0o`)
- `ALLOW_NON_QUAD_IPV4` (2-part legacy dotted format)
- `ALLOW_WILDCARDS`

You can combine flags with bitwise OR:

```php
$flags = ParseFlags::ALLOW_PORT | ParseFlags::ALLOW_ZONE_ID;
```

Default flags include:

- `ALLOW_PORT`
- `ALLOW_ZONE_ID`
- `ALLOW_NON_DECIMAL_IPV4`
- `ALLOW_WILDCARDS`

## Examples

```php
use IPTools\ParseFlags;
use IPTools\Parser;

$a = Parser::ip('[2001:db8::1]:443');
echo (string) $a->ip;  // 2001:db8::1
echo $a->port;         // 443

$b = Parser::ip('fe80::1%eth0');
echo $b->zoneId;       // eth0

echo (string) Parser::ip('0x0a000001')->ip; // 10.0.0.1

$r = Parser::range('192.168.*.*'); // Network 192.168.0.0/16
$x = Parser::any('10.0.0.1-10.0.0.10');

Parser::ip('1.2.3.4:80', ParseFlags::STRICT); // throws
```

## Detailed Behavior

### Port handling

- Bracketed IPv6 with port is supported: `[2001:db8::1]:443`
- IPv4 with port is supported: `10.0.0.1:8080`
- Port must be numeric and between `0` and `65535`

### Zone IDs

- Zone IDs are accepted only when `ALLOW_ZONE_ID` is set
- Zone IDs are valid only for IPv6 inputs (for example `fe80::1%eth0`)

### Non-decimal IPv4

When `ALLOW_NON_DECIMAL_IPV4` is enabled:

- hex: `0x0a000001`
- binary: `0b00001010000000000000000000000001`
- octal: `0o1200000001`

### Wildcards

Aligned wildcard IPv4 expressions produce `Network` instances:

- `192.168.*.*` -> `192.168.0.0/16`

If wildcard alignment does not map to a single CIDR block, parsing falls back to range semantics.

## Return-Type Handling Pattern

```php
$parsed = Parser::any($input);

if ($parsed instanceof IPTools\ParsedAddress) {
    // endpoint logic
} elseif ($parsed instanceof IPTools\Network) {
    // CIDR logic
} else {
    // Range logic
}
```

## Wildcard Behavior

Aligned wildcards are converted to `Network`:

- `192.168.*.*` -> `192.168.0.0/16`

Non-aligned wildcard expressions are handled as range-like input through `Range::parse` behavior where possible.

## Validation Rules

- Zone IDs are only valid for IPv6 addresses.
- Ports must be integer `0..65535`.
- Non-decimal IPv4 values are converted through big-integer math.
- Invalid combinations throw `IPTools\Exception\IpException`.

## Error-First Usage Pattern

```php
try {
    $parsed = Parser::ip($value, ParseFlags::DEFAULT);
} catch (IPTools\Exception\IpException $e) {
    // return validation error to caller
}
```
