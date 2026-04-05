# Parsing

- [Documentation](README.md)

The `Parser` class handles real-world IP input — addresses with ports, IPv6 zone IDs, hex notation, wildcards, and more. Use it when your input comes from users, logs, forms, or network metadata where the format isn't guaranteed to be a clean IP string.

```php
use IPTools\Parser;

$parsed = Parser::ip('192.168.1.1:8080');
echo $parsed->ip;   // 192.168.1.1
echo $parsed->port;  // 8080
```

> **Tip:** If your input is already a clean IP string with no extras, you can use `new IP($string)` or `IP::parse($string)` directly. The `Parser` adds value when there might be ports, zone IDs, or non-standard formats mixed in.

## Parsing IP Addresses

`Parser::ip()` returns a `ParsedAddress` object containing the parsed IP, an optional port, and an optional zone ID:

```php
use IPTools\Parser;

// IPv4 with port
$parsed = Parser::ip('10.0.0.1:443');
echo $parsed->ip;     // 10.0.0.1
echo $parsed->port;   // 443
echo $parsed->zoneId; // null

// IPv6 with port (bracket notation per RFC 3986)
$parsed = Parser::ip('[2001:db8::1]:8080');
echo $parsed->ip;     // 2001:db8::1
echo $parsed->port;   // 8080

// IPv6 with zone ID (interface scope)
$parsed = Parser::ip('fe80::1%eth0');
echo $parsed->ip;     // fe80::1
echo $parsed->zoneId; // eth0

// Plain address (no extras)
$parsed = Parser::ip('8.8.8.8');
echo $parsed->ip;     // 8.8.8.8
echo $parsed->port;   // null
echo $parsed->zoneId; // null
```

> **Note:** `ParsedAddress` is a `readonly` class. The `ip`, `port`, and `zoneId` properties are immutable after creation.

## Parsing Ranges and Networks

`Parser::range()` returns a `Range` or `Network` depending on the input format:

```php
use IPTools\Network;
use IPTools\Range;

$result = Parser::range('192.168.*.*');
// Network('192.168.0.0/16') — wildcards produce Networks

$result = Parser::range('10.0.0.1-10.0.0.100');
// Range('10.0.0.1', '10.0.0.100') — dash-separated produces Ranges

$result = Parser::range('10.0.0.0/24');
// Network('10.0.0.0/24') — CIDR produces Networks

$result = Parser::range('10.0.0.0 255.255.255.0');
// Network('10.0.0.0/24') — dotted netmask produces Networks
```

## The `any()` Auto-Detector

When you don't know whether the input is a single IP or a range/network, use `any()`:

```php
$result = Parser::any($userInput);

if ($result instanceof \IPTools\ParsedAddress) {
    // Single IP — $result->ip, $result->port, $result->zoneId
} elseif ($result instanceof Network) {
    // CIDR or wildcard network
} else {
    // Range (dash-separated)
}
```

The detection logic: if the input contains `-`, `/`, `*`, or whitespace, it's treated as a range/network. Otherwise, it's parsed as a single IP.

## Parse Flags

Flags control which input formats are accepted. They're composable bitflags — combine them with `|`:

```php
use IPTools\ParseFlags;

// Use all sensible defaults
Parser::ip($input, ParseFlags::DEFAULT);

// Strict mode — only standard IP notation, no extras
Parser::ip($input, ParseFlags::STRICT);

// Custom combination
$flags = ParseFlags::ALLOW_PORT | ParseFlags::ALLOW_ZONE_ID;
Parser::ip($input, $flags);
```

### Available Flags

| Flag | What It Allows | In DEFAULT? |
|------|---------------|-------------|
| `ALLOW_PORT` | `192.168.1.1:80`, `[::1]:443` | Yes |
| `ALLOW_ZONE_ID` | `fe80::1%eth0` | Yes |
| `ALLOW_NON_DECIMAL_IPV4` | `0x0a000001`, `0b...`, `0o...` | Yes |
| `ALLOW_NON_QUAD_IPV4` | `10.1` (expands to `10.0.0.1`) | No |
| `ALLOW_WILDCARDS` | `192.168.*.*` | Yes |
| `STRICT` | Nothing extra — clean IPs only | — |

> **Warning:** `ParseFlags::STRICT` is `0` — it disables all optional features. An input like `192.168.1.1:80` will fail in strict mode because the port isn't recognized.

> **Tip:** `ALLOW_NON_QUAD_IPV4` is deliberately excluded from `DEFAULT` because the two-part format (`10.1`) is ambiguous and rarely intended in modern input.

## Non-Decimal IPv4 Formats

When `ALLOW_NON_DECIMAL_IPV4` is enabled (it is by default), the parser accepts whole-address hex, binary, and octal notation:

```php
// Hexadecimal
echo Parser::ip('0x0a000001')->ip; // 10.0.0.1

// Binary
echo Parser::ip('0b00001010000000000000000000000001')->ip; // 10.0.0.1

// Octal
echo Parser::ip('0o1200000001')->ip; // 10.0.0.1
```

These are converted through arbitrary-precision math so they work correctly even for large values.

## Wildcard Support

Aligned wildcard IPv4 expressions are converted to `Network` instances:

```php
echo Parser::range('192.168.*.*'); // 192.168.0.0/16
echo Parser::range('10.*.*.*');    // 10.0.0.0/8
```

Wildcards must be contiguous and trailing — `192.*.1.*` is not valid. Each wildcard octet maps to 8 bits of host space.

## Error Handling

All parser methods throw `IPTools\Exception\IpException` on invalid input:

```php
use IPTools\Exception\IpException;

try {
    $parsed = Parser::ip($userInput);
} catch (IpException $e) {
    // Invalid format — show error to user
    echo "Invalid IP address: {$e->getMessage()}";
}
```

Common error messages:

| Message | Cause |
|---------|-------|
| "Invalid IP address format" | Malformed or empty input |
| "Zone identifiers are not allowed" | Zone ID present but `ALLOW_ZONE_ID` not set |
| "Zone identifiers are only valid for IPv6" | Zone ID on an IPv4 address |
| "Port must be in the range 0-65535" | Port out of bounds |
| "Wildcard parsing is not allowed" | Wildcard present but `ALLOW_WILDCARDS` not set |

## Practical Patterns

### Form Validation

```php
function validateIpInput(string $input): ?string
{
    try {
        $parsed = Parser::ip($input, ParseFlags::ALLOW_PORT);
        return (string) $parsed->ip;
    } catch (IpException) {
        return null; // invalid
    }
}
```

### Log Parsing

```php
// Log line: "Connection from [2001:db8::1]:443 established"
preg_match('/from (.+?) established/', $logLine, $matches);

$parsed = Parser::ip($matches[1]);
$sourceIP = $parsed->ip;
$sourcePort = $parsed->port;
```

## Troubleshooting

### "Invalid IP address format" on Valid-Looking Input

The most common causes:

- **CIDR notation** like `10.0.0.0/24` — use `Parser::range()` or `Network::parse()` instead
- **Whitespace** around the input — `Parser::ip()` trims, but check for hidden characters
- **Non-ASCII characters** — only ASCII digits, dots, colons, brackets, and `%` are valid

### Port Parsing Doesn't Work

Make sure `ParseFlags::ALLOW_PORT` is set (it's included in `DEFAULT`). If you're using `ParseFlags::STRICT`, ports are not recognized.

### Zone ID Rejected

Zone IDs (`fe80::1%eth0`) require `ParseFlags::ALLOW_ZONE_ID` and only work with IPv6 addresses. Passing a zone ID on an IPv4 address always throws, regardless of flags.

## What's Next?

- **[IP Addresses](ip-addresses.md)** — Deep dive into the `IP` class that `ParsedAddress` wraps
- **[Database Storage](database-storage.md)** — Store parsed IPs and ranges in a database
- **[API Reference](api-reference.md)** — Full method listing for `Parser`, `ParseFlags`, and `ParsedAddress`
