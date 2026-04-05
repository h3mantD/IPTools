<?php

declare(strict_types=1);

namespace IPTools;

use InvalidArgumentException;
use IPTools\Enums\IPType;
use IPTools\Enums\IPVersion;
use IPTools\Enums\OverflowMode;
use IPTools\Exception\IpException;
use OverflowException;
use Stringable;
use Throwable;

/**
 * Immutable value object representing a single IPv4 or IPv6 address.
 *
 * Internally stores the address as a packed binary string (PHP's inet_pton format):
 * 4 bytes for IPv4, 16 bytes for IPv6. This binary form enables fast byte-level
 * comparisons and bitwise operations without string parsing.
 *
 * All arithmetic methods (next, previous, addOffset, shift) return new IP instances —
 * the original is never mutated.
 *
 * @author Safarov Alisher <alisher.safarov@outlook.com>
 *
 * @link https://github.com/S1lentium/IPTools
 */
class IP implements Stringable
{
    use PropertyTrait;

    /** @deprecated Use IPVersion::IPv4 instead. */
    public const IP_V4 = 'IPv4';

    /** @deprecated Use IPVersion::IPv6 instead. */
    public const IP_V6 = 'IPv6';

    /** @deprecated Use IPVersion::IPv4->maxPrefixLength() instead. */
    public const IP_V4_MAX_PREFIX_LENGTH = 32;

    /** @deprecated Use IPVersion::IPv6->maxPrefixLength() instead. */
    public const IP_V6_MAX_PREFIX_LENGTH = 128;

    /** @deprecated Use IPVersion::IPv4->octets() instead. */
    public const IP_V4_OCTETS = 4;

    /** @deprecated Use IPVersion::IPv6->octets() instead. */
    public const IP_V6_OCTETS = 16;

    /**
     * @var numeric-string
     *
     * @deprecated Use IPVersion::IPv4->maxLong() instead.
     */
    public const IP_V4_MAX_LONG = '4294967295';

    /**
     * @var numeric-string
     *
     * @deprecated Use IPVersion::IPv6->maxLong() instead.
     */
    public const IP_V6_MAX_LONG = '340282366920938463463374607431768211455';

    /** Packed binary from inet_pton: 4 bytes (IPv4) or 16 bytes (IPv6). */
    private string $in_addr;

    /**
     * @throws IpException
     */
    public function __construct(string $ip)
    {
        $inAddr = inet_pton($ip);
        if ($inAddr === false) {
            throw new IpException('Invalid IP address format');
        }
        $this->in_addr = $inAddr;
    }

    public function __toString(): string
    {
        return (string) inet_ntop($this->in_addr);
    }

    /**
     * Smart factory: detect format and dispatch to the appropriate parser.
     *
     * Resolution order: 0x → hex, 0b → binary, numeric → long, otherwise → inet_pton.
     */
    public static function parse(int|string $ip): self
    {
        if (is_string($ip)) {
            if (str_starts_with($ip, '0x')) {
                $ip = substr($ip, 2);

                return self::parseHex($ip);
            }

            if (str_starts_with($ip, '0b')) {
                $ip = substr($ip, 2);

                return self::parseBin($ip);
            }
        }

        if (is_numeric($ip)) {
            return self::parseLong($ip);
        }

        return new self($ip);
    }

    /**
     * @throws IpException
     */
    public static function parseBin(string $binIP): self
    {
        if (! preg_match('/^([0-1]{32}|[0-1]{128})$/', $binIP)) {
            throw new IpException('Invalid binary IP address format');
        }

        $in_addr = '';
        foreach (array_map(bindec(...), str_split($binIP, 8)) as $char) {
            $in_addr .= pack('C*', $char);
        }

        $ip = inet_ntop($in_addr);
        if ($ip === false) {
            throw new IpException('Invalid IP address format');
        }

        return new self($ip);
    }

    /**
     * @throws IpException
     */
    public static function parseHex(string $hexIP): self
    {
        if (! preg_match('/^([0-9a-fA-F]{8}|[0-9a-fA-F]{32})$/', $hexIP)) {
            throw new IpException('Invalid hexadecimal IP address format');
        }

        $ip = inet_ntop(pack('H*', $hexIP));
        if ($ip === false) {
            throw new IpException('Invalid IP address format');
        }

        return new self($ip);
    }

    /**
     * Construct from a decimal integer (long) representation.
     *
     * Uses bcmath throughout because IPv6 values can reach 2^128,
     * far exceeding PHP_INT_MAX. The decimal is converted to a packed
     * binary string by repeated division by 256 (one byte per iteration).
     *
     * @throws IpException
     */
    public static function parseLong(int|string $longIP, IPVersion|string $version = IPVersion::IPv4): self
    {
        $version = IPVersion::resolve($version);
        $longIP = (string) $longIP;
        if (! preg_match('/^-?\d+$/', $longIP)) {
            throw new IpException('Invalid long IP address format');
        }
        /** @var numeric-string $longIP */
        $max = $version->maxLong();
        if (bccomp($longIP, '0') < 0 || bccomp($longIP, $max) > 0) {
            throw new IpException('Long IP address is out of range');
        }

        // Convert decimal → byte array by extracting one octet at a time (LSB first)
        $binary = [];
        $octets = $version->octets();
        for ($i = 0; $i < $octets; $i++) {
            $binary[] = (int) bcmod($longIP, '256');
            $longIP = bcdiv($longIP, '256', 0);
        }
        // Reverse to network byte order (MSB first) and pack into binary string
        $packed = pack('C*', ...array_reverse($binary));
        $ip = inet_ntop($packed);
        if ($ip === false) {
            throw new IpException('Invalid IP address format');
        }

        return new self($ip);
    }

    public static function parseInAddr(string $inAddr): self
    {
        $ip = inet_ntop($inAddr);
        if ($ip === false) {
            throw new IpException('Invalid IP address format');
        }

        return new self($ip);
    }

    /**
     * Embed an IPv4 address in IPv6 as ::ffff:x.x.x.x (RFC 4291 §2.5.5.2).
     *
     * Used by dual-stack systems to represent IPv4 clients within IPv6 sockets.
     *
     * @throws InvalidArgumentException
     */
    public static function toIpv4Mapped(self $ipv4): self
    {
        if ($ipv4->getVersion() !== IPVersion::IPv4) {
            throw new InvalidArgumentException('Expected an IPv4 address');
        }

        return self::parseInAddr(str_repeat("\x00", 10)."\xff\xff".$ipv4->inAddr());
    }

    /**
     * Extract the IPv4 address from an IPv4-mapped IPv6 address (::ffff:x.x.x.x).
     *
     * @throws InvalidArgumentException
     */
    public static function fromIpv4Mapped(self|string $ipv6): self
    {
        $ip = is_string($ipv6) ? self::parse($ipv6) : $ipv6;
        if ($ip->getVersion() !== IPVersion::IPv6 || ! $ip->isIpv4Mapped()) {
            throw new InvalidArgumentException('Address is not an IPv4-mapped IPv6 address');
        }

        return self::parseInAddr(substr($ip->inAddr(), 12, IPVersion::IPv4->octets()));
    }

    /**
     * Create a 6to4 IPv6 address (2002:x.x.x.x::) from an IPv4 address (RFC 3056).
     *
     * @throws InvalidArgumentException
     */
    public static function to6to4(self $ipv4): self
    {
        if ($ipv4->getVersion() !== IPVersion::IPv4) {
            throw new InvalidArgumentException('Expected an IPv4 address');
        }

        return self::parseInAddr("\x20\x02".$ipv4->inAddr().str_repeat("\x00", 10));
    }

    /**
     * Extract the embedded IPv4 address from a 6to4 IPv6 address (RFC 3056).
     *
     * @throws InvalidArgumentException
     */
    public static function from6to4(self|string $ipv6): self
    {
        $ip = is_string($ipv6) ? self::parse($ipv6) : $ipv6;
        if ($ip->getVersion() !== IPVersion::IPv6 || ! $ip->is6to4()) {
            throw new InvalidArgumentException('Address is not a 6to4 IPv6 address');
        }

        return self::parseInAddr(substr($ip->inAddr(), 2, IPVersion::IPv4->octets()));
    }

    /**
     * Create a NAT64 IPv6 address by embedding IPv4 in the last 32 bits of a /96 prefix (RFC 6052).
     *
     * Default prefix is the well-known 64:ff9b::/96.
     *
     * @throws InvalidArgumentException
     */
    public static function toNat64(self $ipv4, string $prefix = '64:ff9b::/96'): self
    {
        if ($ipv4->getVersion() !== IPVersion::IPv4) {
            throw new InvalidArgumentException('Expected an IPv4 address');
        }

        $network = self::parseNat64Prefix($prefix);

        return self::parseInAddr(substr($network->getNetwork()->inAddr(), 0, 12).$ipv4->inAddr());
    }

    /**
     * Extract the IPv4 address from a NAT64 IPv6 address (RFC 6052).
     *
     * @throws InvalidArgumentException
     */
    public static function fromNat64(self|string $ipv6, string $prefix = '64:ff9b::/96'): self
    {
        $ip = is_string($ipv6) ? self::parse($ipv6) : $ipv6;
        if ($ip->getVersion() !== IPVersion::IPv6) {
            throw new InvalidArgumentException('Expected an IPv6 address');
        }

        $network = self::parseNat64Prefix($prefix);
        if (! $network->containsIP($ip)) {
            throw new InvalidArgumentException('Address is not within the NAT64 prefix');
        }

        return self::parseInAddr(substr($ip->inAddr(), 12, IPVersion::IPv4->octets()));
    }

    public function getVersion(): IPVersion
    {
        return match (strlen($this->in_addr)) {
            4 => IPVersion::IPv4,
            16 => IPVersion::IPv6,
            default => throw new IpException('Unable to determine IP version'),
        };
    }

    public function getMaxPrefixLength(): int
    {
        return $this->getVersion()->maxPrefixLength();
    }

    public function getOctetsCount(): int
    {
        return $this->getVersion()->octets();
    }

    public function getReversePointer(): string
    {
        if ($this->getVersion() === IPVersion::IPv4) {
            $reverseOctets = array_reverse(explode('.', $this->__toString()));

            return implode('.', $reverseOctets).'.in-addr.arpa';
        }
        $unpacked = unpack('H*hex', $this->in_addr);
        if ($unpacked === false || ! isset($unpacked['hex'])) {
            throw new IpException('Unable to unpack IP address');
        }
        /** @var array{hex: string} $unpacked */
        $reverseOctets = array_reverse(str_split($unpacked['hex']));

        return implode('.', $reverseOctets).'.ip6.arpa';
    }

    /**
     * Raw packed binary (inet_pton format). Used internally for byte-level
     * comparisons; prefer __toString() for display.
     */
    public function inAddr(): string
    {
        return $this->in_addr;
    }

    public function toBin(): string
    {
        $binary = [];
        $unpacked = unpack('C*', $this->in_addr);
        if ($unpacked === false) {
            throw new IpException('Unable to unpack IP address');
        }
        /** @var array<int, int> $unpacked */
        foreach ($unpacked as $char) {
            $binary[] = str_pad(decbin($char), 8, '0', STR_PAD_LEFT);
        }

        return implode('', $binary);
    }

    public function toHex(): string
    {
        return bin2hex($this->in_addr);
    }

    public function expanded(): string
    {
        if ($this->getVersion() === IPVersion::IPv4) {
            return (string) $this;
        }

        return implode(':', str_split($this->toHex(), 4));
    }

    /**
     * Decimal string representation of the address.
     *
     * Returns a numeric string rather than int because IPv6 values exceed
     * PHP_INT_MAX. IPv4 uses native ip2long(); IPv6 uses bcmath to
     * reconstruct the value from its 16 bytes (MSB-first positional expansion).
     *
     * @return numeric-string
     */
    public function toLong(): string
    {
        if ($this->getVersion() === IPVersion::IPv4) {
            $ip = inet_ntop($this->in_addr);
            if ($ip === false) {
                throw new IpException('Unable to unpack IP address');
            }
            $longValue = ip2long($ip);
            if ($longValue === false) {
                throw new IpException('Unable to convert IP address');
            }

            // %u ensures unsigned — ip2long returns signed on 32-bit systems
            return sprintf('%u', $longValue);
        }

        // IPv6: each byte contributes byte_value × 256^position (big-endian)
        $long = '0';
        $octet = IPVersion::IPv6->octets() - 1;
        $chars = unpack('C*', $this->in_addr);
        if ($chars === false) {
            throw new IpException('Unable to unpack IP address');
        }
        /** @var array<int, int> $chars */
        foreach ($chars as $char) {
            $long = bcadd($long, bcmul((string) $char, bcpow('256', (string) $octet--)));
        }

        return $long;
    }

    /**
     * All IANA-classified types that apply to this address, in precedence order.
     *
     * @return list<IPType>
     */
    public function types(): array
    {
        return TypeRegistry::classify($this);
    }

    /**
     * Primary type based on IANA precedence order.
     */
    public function primaryType(): IPType
    {
        return $this->types()[0];
    }

    public function is(IPType $type): bool
    {
        return in_array($type, $this->types(), true);
    }

    /**
     * True if the address is globally routable unicast (not private/reserved/etc.).
     */
    public function isGlobalRoutable(): bool
    {
        return $this->primaryType() === IPType::GLOBAL;
    }

    public function isPrivate(): bool
    {
        $types = $this->types();

        return in_array(IPType::PRIVATE, $types, true) || in_array(IPType::UNIQUE_LOCAL, $types, true);
    }

    public function isLoopback(): bool
    {
        return $this->is(IPType::LOOPBACK);
    }

    public function isMulticast(): bool
    {
        return $this->is(IPType::MULTICAST);
    }

    public function isLinkLocal(): bool
    {
        return $this->is(IPType::LINK_LOCAL);
    }

    public function isDocumentation(): bool
    {
        return $this->is(IPType::DOCUMENTATION);
    }

    public function isReserved(): bool
    {
        return $this->is(IPType::RESERVED);
    }

    public function isIpv4Mapped(): bool
    {
        if ($this->getVersion() !== IPVersion::IPv6) {
            return false;
        }

        return str_starts_with($this->in_addr, str_repeat("\x00", 10)."\xff\xff");
    }

    public function is6to4(): bool
    {
        if ($this->getVersion() !== IPVersion::IPv6) {
            return false;
        }

        return str_starts_with($this->in_addr, "\x20\x02");
    }

    /**
     * @throws InvalidArgumentException
     */
    public function isNat64(string $prefix = '64:ff9b::/96'): bool
    {
        if ($this->getVersion() !== IPVersion::IPv6) {
            return false;
        }

        $network = self::parseNat64Prefix($prefix);

        return $network->containsIP($this);
    }

    // -------------------------------------------------------------------------
    // IP Arithmetic and Offset Operations
    // -------------------------------------------------------------------------

    /**
     * Compare to another IP of the same version.
     * Returns -1, 0, or 1.
     *
     * @throws InvalidArgumentException if versions differ
     */
    public function compareTo(self $other): int
    {
        if ($this->getVersion() !== $other->getVersion()) {
            throw new InvalidArgumentException('Cannot compare IPs of different versions');
        }

        // strcmp returns variable positive/negative values; <=> 0 normalizes to -1/0/1
        return strcmp($this->in_addr, $other->in_addr) <=> 0;
    }

    /**
     * Signed distance from this to $other: $other - $this.
     * Result is a decimal string (may be negative).
     *
     * @return numeric-string
     *
     * @throws InvalidArgumentException if versions differ
     */
    public function distanceTo(self $other): string
    {
        if ($this->getVersion() !== $other->getVersion()) {
            throw new InvalidArgumentException('Cannot compute distance between IPs of different versions');
        }

        return bcsub($other->toLong(), $this->toLong());
    }

    /**
     * Add a signed integer offset to this address.
     *
     * @throws OverflowException when mode is THROW and result is out of range
     */
    public function addOffset(int|string $delta, OverflowMode $mode = OverflowMode::THROW): ?self
    {
        /** @var numeric-string $delta */
        $delta = (string) $delta;
        $result = bcadd($this->toLong(), $delta);
        $max = $this->maxLong();

        $overflow = bccomp($result, $max) > 0;
        $underflow = bccomp($result, '0') < 0;

        if ($overflow || $underflow) {
            return match ($mode) {
                OverflowMode::THROW => throw new OverflowException(
                    sprintf('IP address offset overflow (delta: %s)', $delta)
                ),
                OverflowMode::NULL => null,
                OverflowMode::WRAP => self::parseLong(
                    $this->wrapLong($result, $max),
                    $this->getVersion()
                ),
                OverflowMode::CLAMP => self::parseLong(
                    $underflow ? '0' : $max,
                    $this->getVersion()
                ),
            };
        }

        return self::parseLong($result, $this->getVersion());
    }

    /**
     * Return the address $steps ahead. Returns null at the address-space boundary.
     *
     * @throws IpException if steps is negative
     */
    public function next(int|string $steps = 1): ?self
    {
        /** @var numeric-string $s */
        $s = (string) $steps;
        if (bccomp($s, '0') < 0) {
            throw new IpException('Number must be non-negative');
        }

        return $this->addOffset($s, OverflowMode::NULL);
    }

    /**
     * Return the address $steps behind. Returns null at the address-space boundary.
     *
     * @throws IpException if steps is negative
     */
    public function previous(int|string $steps = 1): ?self
    {
        /** @var numeric-string $s */
        $s = (string) $steps;
        if (bccomp($s, '0') < 0) {
            throw new IpException('Number must be non-negative');
        }

        return $this->addOffset('-'.$s, OverflowMode::NULL);
    }

    /**
     * Shift bits right (positive $bits) or left (negative $bits).
     * Right shifts never overflow. Left shifts may overflow.
     *
     * @throws OverflowException when mode is THROW and a left-shift overflows
     */
    public function shift(int $bits, OverflowMode $mode = OverflowMode::THROW): ?self
    {
        if ($bits === 0) {
            return new self((string) $this);
        }

        $value = $this->toLong();
        $max = $this->maxLong();

        if ($bits > 0) {
            // Right shift: value >> bits = floor(value / 2^bits); always in [0, max]
            $result = bcdiv($value, bcpow('2', (string) $bits), 0);

            return self::parseLong($result, $this->getVersion());
        }

        // Left shift: value << (-bits) = value * 2^(-bits); may overflow
        $absBits = (string) (-$bits);
        $result = bcmul($value, bcpow('2', $absBits));

        if (bccomp($result, $max) > 0) {
            return match ($mode) {
                OverflowMode::THROW => throw new OverflowException(
                    sprintf('IP address bit-shift overflow (bits: %d)', $bits)
                ),
                OverflowMode::NULL => null,
                OverflowMode::WRAP => self::parseLong(
                    $this->wrapLong($result, $max),
                    $this->getVersion()
                ),
                OverflowMode::CLAMP => self::parseLong($max, $this->getVersion()),
            };
        }

        return self::parseLong($result, $this->getVersion());
    }

    /**
     * @throws InvalidArgumentException
     */
    private static function parseNat64Prefix(string $prefix): Network
    {
        try {
            $network = Network::parse($prefix);
        } catch (Throwable $throwable) {
            throw new InvalidArgumentException('Invalid NAT64 prefix', 0, $throwable);
        }

        if ($network->getIP()->getVersion() !== IPVersion::IPv6 || $network->getPrefixLength() !== 96) {
            throw new InvalidArgumentException('NAT64 prefix must be an IPv6 /96 network');
        }

        return $network;
    }

    // -------------------------------------------------------------------------

    /**
     * Maximum numeric value for this IP version as a decimal string.
     *
     * @return numeric-string
     */
    private function maxLong(): string
    {
        return $this->getVersion()->maxLong();
    }

    /**
     * Wrap a value into [0, max] using modulo arithmetic.
     *
     * PHP's bcmod preserves the sign of the dividend (unlike mathematical modulo),
     * so for negative inputs we add the modulus to bring the result back into [0, max].
     * Example: -1 mod 4294967296 = -1 in PHP → corrected to 4294967295.
     *
     * @param  numeric-string  $value
     * @param  numeric-string  $max
     * @return numeric-string
     */
    private function wrapLong(string $value, string $max): string
    {
        $modulus = bcadd($max, '1');
        /** @var numeric-string $result */
        $result = bcmod($value, $modulus);

        if (bccomp($result, '0') < 0) {
            return bcadd($result, $modulus);
        }

        return $result;
    }
}
