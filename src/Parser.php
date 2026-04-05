<?php

declare(strict_types=1);

namespace IPTools;

use InvalidArgumentException;
use IPTools\Enums\IPVersion;
use IPTools\Exception\IpException;

/**
 * Flexible input parser for IP addresses, ranges, and networks.
 *
 * Supports standard notation plus optional extras controlled by ParseFlags:
 *   - Ports:           `192.168.1.1:80`, `[::1]:443`
 *   - Zone IDs:        `fe80::1%eth0`
 *   - Wildcards:       `192.168.*.*` → Network
 *   - Non-decimal IPv4: `0x0a000001`, `0b...`, `0o...`
 *   - Non-quad IPv4:   `10.1` → `10.0.0.1`
 *   - Ranges:          `10.0.0.1-10.0.0.100`
 *   - CIDR:            `10.0.0.0/24`
 *   - Dotted netmask:  `10.0.0.0 255.255.255.0`
 */
final class Parser
{
    /**
     * Parse a single IP address with optional port and zone ID.
     *
     * @throws IpException
     */
    public static function ip(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress
    {
        $input = trim($input);
        if ($input === '') {
            throw new IpException('Invalid IP address format');
        }

        $port = null;
        $addressPart = $input;

        // Extract port: [IPv6]:port (RFC 3986 bracket notation) or IPv4:port
        if (self::hasFlag($flags, ParseFlags::ALLOW_PORT)) {
            if (preg_match('/^\[(.+)]:(\d+)$/', $input, $matches) === 1) {
                $addressPart = $matches[1];
                $port = self::parsePort($matches[2]);
            } elseif (preg_match('/^([^:]+):(\d+)$/', $input, $matches) === 1) {
                $addressPart = $matches[1];
                $port = self::parsePort($matches[2]);
            }
        }

        // Extract zone ID (interface scope for IPv6 link-local, e.g. fe80::1%eth0)
        $zoneId = null;
        if (str_contains($addressPart, '%')) {
            if (! self::hasFlag($flags, ParseFlags::ALLOW_ZONE_ID)) {
                throw new IpException('Zone identifiers are not allowed');
            }

            [$addressPart, $zoneId] = explode('%', $addressPart, 2);
            if ($zoneId === '') {
                throw new IpException('Invalid IPv6 zone identifier');
            }
        }

        $ip = self::parseAddress($addressPart, $flags);

        if ($zoneId !== null && $ip->getVersion() !== IPVersion::IPv6) {
            throw new IpException('Zone identifiers are only valid for IPv6 addresses');
        }

        return new ParsedAddress($ip, $port, $zoneId);
    }

    /**
     * Parse a range or network from string notation.
     *
     * Dispatch order: wildcards → dash ranges → CIDR/dotted-netmask → single IP as /32 or /128.
     *
     * @throws IpException
     */
    public static function range(string $input, int $flags = ParseFlags::DEFAULT): Range|Network
    {
        $input = trim($input);
        if ($input === '') {
            throw new IpException('Invalid range/network format');
        }

        if (str_contains($input, '*')) {
            if (! self::hasFlag($flags, ParseFlags::ALLOW_WILDCARDS)) {
                throw new IpException('Wildcard parsing is not allowed');
            }

            $wildcardNetwork = self::wildcardToNetwork($input);
            if ($wildcardNetwork instanceof Network) {
                return $wildcardNetwork;
            }
        }

        if (str_contains($input, '-')) {
            return Range::parse($input);
        }

        if (str_contains($input, '/') || str_contains($input, ' ')) {
            return Network::parse($input);
        }

        return Range::parse($input);
    }

    /**
     * Auto-detect input type: returns ParsedAddress for single IPs,
     * or Range/Network for ranges, CIDRs, wildcards, and dotted-netmask notation.
     *
     * @throws IpException
     */
    public static function any(string $input, int $flags = ParseFlags::DEFAULT): ParsedAddress|Range|Network
    {
        $trimmed = trim($input);

        if (str_contains($trimmed, '-') || str_contains($trimmed, '/') || str_contains($trimmed, '*')) {
            return self::range($trimmed, $flags);
        }

        if (preg_match('/\s/', $trimmed) === 1) {
            return self::range($trimmed, $flags);
        }

        return self::ip($trimmed, $flags);
    }

    /**
     * Parse the address portion after port/zone extraction.
     *
     * Handles non-decimal formats (hex/binary/octal) and the legacy
     * 2-part non-quad notation (`10.1` → `10.0.0.1`) before falling
     * through to PHP's inet_pton via the IP constructor.
     *
     * @throws IpException
     */
    private static function parseAddress(string $address, int $flags): IP
    {
        $address = trim($address);
        if ($address === '') {
            throw new IpException('Invalid IP address format');
        }

        // Non-decimal whole-address formats: 0xHEX, 0bBINARY, 0oOCTAL → long integer → IP
        if (self::hasFlag($flags, ParseFlags::ALLOW_NON_DECIMAL_IPV4)) {
            if (preg_match('/^0x([0-9a-fA-F]+)$/', $address, $matches) === 1) {
                return IP::parseLong(self::baseToDecimalString($matches[1], 16));
            }

            if (preg_match('/^0b([01]+)$/', $address, $matches) === 1) {
                return IP::parseLong(self::baseToDecimalString($matches[1], 2));
            }

            if (preg_match('/^0o([0-7]+)$/', $address, $matches) === 1) {
                return IP::parseLong(self::baseToDecimalString($matches[1], 8));
            }
        }

        // 2-part shorthand: "10.1" → "10.0.0.1" (first octet + last octet, middle zeroed)
        if (self::hasFlag($flags, ParseFlags::ALLOW_NON_QUAD_IPV4)
            && preg_match('/^\d+\.\d+$/', $address) === 1
        ) {
            [$first, $last] = explode('.', $address, 2);

            $firstPart = filter_var($first, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => 255],
            ]);
            $lastPart = filter_var($last, FILTER_VALIDATE_INT, [
                'options' => ['min_range' => 0, 'max_range' => 255],
            ]);

            if ($firstPart === false || $lastPart === false) {
                throw new IpException('Invalid non-quad IPv4 format');
            }

            return new IP(sprintf('%d.0.0.%d', $firstPart, $lastPart));
        }

        return new IP($address);
    }

    /**
     * Convert wildcard notation like `192.168.*.*` to a CIDR network.
     *
     * Wildcards must be contiguous and trailing (no gaps like `192.*.1.*`).
     * Each wildcard octet maps to 8 bits of host space, so the prefix length
     * is simply (number of fixed octets) * 8.
     *
     * Returns null if the pattern is malformed or has non-trailing wildcards.
     */
    private static function wildcardToNetwork(string $input): ?Network
    {
        if (preg_match('/^([0-9*]+)\.([0-9*]+)\.([0-9*]+)\.([0-9*]+)$/', $input) !== 1) {
            return null;
        }

        $parts = explode('.', $input);
        $firstWildcardIndex = array_search('*', $parts, true);
        if ($firstWildcardIndex === false) {
            return null;
        }

        // Validate: fixed octets must all come before the first wildcard
        foreach ($parts as $index => $part) {
            if ($part === '*') {
                continue;
            }

            if (! ctype_digit($part)) {
                return null;
            }

            $value = (int) $part;
            if ($value > 255) {
                return null;
            }

            // No fixed octets allowed after the first wildcard
            if ($index > $firstWildcardIndex) {
                return null;
            }
        }

        // All positions from firstWildcard onward must be wildcards
        for ($i = $firstWildcardIndex; $i < 4; $i++) {
            if ($parts[$i] !== '*') {
                return null;
            }
        }

        $ip = implode('.', array_map(static fn (string $part): string => $part === '*' ? '0' : $part, $parts));
        $prefix = $firstWildcardIndex * 8;

        return Network::parse(sprintf('%s/%d', $ip, $prefix));
    }

    /**
     * Convert a digit string in any base (2, 8, 16) to a decimal string.
     *
     * Uses bcmath for arbitrary precision so hex addresses like
     * 0xFFFFFFFF (4294967295) don't overflow PHP's native int.
     *
     * @return numeric-string
     */
    private static function baseToDecimalString(string $digits, int $base): string
    {
        $digits = strtolower($digits);
        $result = '0';

        foreach (str_split($digits) as $digit) {
            $value = match (true) {
                $digit >= '0' && $digit <= '9' => ord($digit) - ord('0'),
                $digit >= 'a' && $digit <= 'f' => ord($digit) - ord('a') + 10,
                default => throw new InvalidArgumentException('Invalid digit in numeric literal'),
            };

            if ($value >= $base) {
                throw new InvalidArgumentException('Invalid digit in numeric literal');
            }

            $result = bcadd(bcmul($result, (string) $base), (string) $value);
        }

        return $result;
    }

    private static function hasFlag(int $flags, int $flag): bool
    {
        return ($flags & $flag) !== 0;
    }

    /**
     * @throws IpException
     */
    private static function parsePort(string $port): int
    {
        if (strlen($port) > 5) {
            throw new IpException('Port must be in the range 0-65535');
        }

        $parsedPort = (int) $port;
        if ($parsedPort > 65535) {
            throw new IpException('Port must be in the range 0-65535');
        }

        return $parsedPort;
    }
}
