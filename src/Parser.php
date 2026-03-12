<?php

declare(strict_types=1);

namespace IPTools;

use InvalidArgumentException;
use IPTools\Exception\IpException;

final class Parser
{
    /**
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

        if (self::hasFlag($flags, ParseFlags::ALLOW_PORT)) {
            if (preg_match('/^\[(.+)]:(\d+)$/', $input, $matches) === 1) {
                $addressPart = $matches[1];
                $port = self::parsePort($matches[2]);
            } elseif (preg_match('/^([^:]+):(\d+)$/', $input, $matches) === 1) {
                $addressPart = $matches[1];
                $port = self::parsePort($matches[2]);
            }
        }

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

        if ($zoneId !== null && $ip->getVersion() !== IP::IP_V6) {
            throw new IpException('Zone identifiers are only valid for IPv6 addresses');
        }

        return new ParsedAddress($ip, $port, $zoneId);
    }

    /**
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
     * @throws IpException
     */
    private static function parseAddress(string $address, int $flags): IP
    {
        $address = trim($address);
        if ($address === '') {
            throw new IpException('Invalid IP address format');
        }

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

        if (self::hasFlag($flags, ParseFlags::ALLOW_NON_QUAD_IPV4)
            && preg_match('/^\d+\.\d+$/', $address) === 1
        ) {
            [$first, $last] = explode('.', $address, 2);
            if ((int) $first < 0 || (int) $first > 255 || (int) $last < 0 || (int) $last > 255) {
                throw new IpException('Invalid non-quad IPv4 format');
            }

            return new IP(sprintf('%d.0.0.%d', (int) $first, (int) $last));
        }

        return new IP($address);
    }

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

        foreach ($parts as $index => $part) {
            if ($part === '*') {
                continue;
            }

            if (! ctype_digit($part)) {
                return null;
            }

            $value = (int) $part;
            if ($value < 0 || $value > 255) {
                return null;
            }

            if ($index > $firstWildcardIndex) {
                return null;
            }
        }

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
        if (! ctype_digit($port)) {
            throw new IpException('Invalid port format');
        }

        $parsedPort = (int) $port;
        if ($parsedPort < 0 || $parsedPort > 65535) {
            throw new IpException('Port must be in the range 0-65535');
        }

        return $parsedPort;
    }
}
