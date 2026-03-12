# Parser Guide

Back to index: [Documentation Index](README.md)

`IPTools\Parser` provides controlled parsing for addresses, ranges, ports, zones, and wildcards.

## Main Entry Points

- `Parser::ip(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress`
- `Parser::range(string $input, int $flags = ParseFlags::DEFAULT): Range|Network`
- `Parser::any(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress|Range|Network`

## ParsedAddress

`ParsedAddress` is a readonly wrapper:

```php
final readonly class ParsedAddress {
    public IP $ip;
    public ?int $port;
    public ?string $zoneId;
}
```

## Parse Flags

Use `IPTools\ParseFlags` as bit flags.

- `STRICT` - disables optional parsing features
- `ALLOW_PORT`
- `ALLOW_ZONE_ID`
- `ALLOW_NON_DECIMAL_IPV4` (`0x`, `0b`, `0o`)
- `ALLOW_NON_QUAD_IPV4` (2-part legacy dotted format)
- `ALLOW_WILDCARDS`

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

## Wildcard Behavior

Aligned wildcards are converted to `Network`:

- `192.168.*.*` -> `192.168.0.0/16`

Non-aligned wildcard expressions are handled as range-like input through `Range::parse` behavior where possible.

## Validation Rules

- Zone IDs are only valid for IPv6 addresses.
- Ports must be integer `0..65535`.
- Non-decimal IPv4 values are converted through big-integer math.
- Invalid combinations throw `IPTools\Exception\IpException`.
