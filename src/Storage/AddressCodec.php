<?php

declare(strict_types=1);

namespace IPTools\Storage;

use InvalidArgumentException;
use IPTools\Enums\IPVersion;
use IPTools\IP;

/**
 * Normalizes IP addresses to 16-byte binary for uniform database storage.
 *
 * IPv6 addresses are already 16 bytes (inet_pton output). IPv4 addresses
 * are padded with 12 leading zero bytes to match. This allows a single
 * BINARY(16) column and composite index to handle both versions with
 * simple byte-range comparisons.
 */
final class AddressCodec
{
    /** Encode an IP to 16-byte binary (IPv4 is zero-padded to 16 bytes). */
    public static function to16(IP $ip): string
    {
        if ($ip->getVersion() === IPVersion::IPv4) {
            return str_repeat("\x00", 12).$ip->inAddr();
        }

        return $ip->inAddr();
    }

    /** Decode 16-byte binary back to an IP, using the version hint to extract the right bytes. */
    public static function from16(string $bin, int $version): IP
    {
        if (strlen($bin) !== 16) {
            throw new InvalidArgumentException('Address binary value must be exactly 16 bytes');
        }

        if ($version === 4) {
            // IPv4 occupies the last 4 bytes (first 12 are zero padding)
            return IP::parseInAddr(substr($bin, 12, 4));
        }

        if ($version === 6) {
            return IP::parseInAddr($bin);
        }

        throw new InvalidArgumentException('Version must be 4 or 6');
    }
}
