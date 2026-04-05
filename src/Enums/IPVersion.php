<?php

declare(strict_types=1);

namespace IPTools\Enums;

/**
 * IP address version with associated protocol constants.
 *
 * Centralizes per-version values (prefix length, octet count, max long)
 * that were previously scattered across IP class constants.
 */
enum IPVersion: string
{
    case IPv4 = 'IPv4';
    case IPv6 = 'IPv6';

    /**
     * Resolve from a string value for backward compatibility.
     *
     * Accepts 'IPv4', 'IPv6', or an IPVersion instance (passthrough).
     */
    public static function resolve(self|string $version): self
    {
        if ($version instanceof self) {
            return $version;
        }

        return self::from($version);
    }

    /** Maximum CIDR prefix length (32 for IPv4, 128 for IPv6). */
    public function maxPrefixLength(): int
    {
        return match ($this) {
            self::IPv4 => 32,
            self::IPv6 => 128,
        };
    }

    /** Number of octets in the packed binary representation. */
    public function octets(): int
    {
        return match ($this) {
            self::IPv4 => 4,
            self::IPv6 => 16,
        };
    }

    /**
     * Maximum decimal value for this address version.
     *
     * @return numeric-string
     */
    public function maxLong(): string
    {
        return match ($this) {
            self::IPv4 => '4294967295',
            self::IPv6 => '340282366920938463463374607431768211455',
        };
    }

    /** Integer version number for database storage (4 or 6). */
    public function toInt(): int
    {
        return match ($this) {
            self::IPv4 => 4,
            self::IPv6 => 6,
        };
    }
}
