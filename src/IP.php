<?php

declare(strict_types=1);

namespace IPTools;

use IPTools\Exception\IpException;
use Stringable;

/**
 * @author Safarov Alisher <alisher.safarov@outlook.com>
 *
 * @link https://github.com/S1lentium/IPTools
 */
final class IP implements Stringable
{
    use PropertyTrait;

    public const IP_V4 = 'IPv4';

    public const IP_V6 = 'IPv6';

    public const IP_V4_MAX_PREFIX_LENGTH = 32;

    public const IP_V6_MAX_PREFIX_LENGTH = 128;

    public const IP_V4_OCTETS = 4;

    public const IP_V6_OCTETS = 16;

    private string $in_addr;

    /**
     * @throws IpException
     */
    public function __construct(string|int|float $ip)
    {
        $ip = (string) $ip;
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new IpException('Invalid IP address format');
        }
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

    public static function parse(int|string $ip): self
    {
        if (is_string($ip)) {
            if (str_starts_with($ip, '0x')) {
                $ip = mb_substr($ip, 2);

                return self::parseHex($ip);
            }

            if (str_starts_with($ip, '0b')) {
                $ip = mb_substr($ip, 2);

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
        foreach (array_map('bindec', mb_str_split($binIP, 8)) as $char) {
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

    public static function parseLong(int|string $longIP, string $version = self::IP_V4): self
    {
        if ($version === self::IP_V4) {
            $ip = long2ip((int) $longIP);

            return new self($ip);
        }
        $binary = [];
        $longIP = (string) $longIP;
        for ($i = 0; $i < self::IP_V6_OCTETS; $i++) {
            $binary[] = (int) bcmod($longIP, '256');
            $longIP = bcdiv($longIP, '256', 0);
        }
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

    public function getVersion(): string
    {
        $version = '';
        $ip = inet_ntop($this->in_addr);
        if ($ip === false) {
            throw new IpException('Unable to unpack IP address');
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $version = self::IP_V4;
        } elseif (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $version = self::IP_V6;
        }

        return $version;
    }

    public function getMaxPrefixLength(): int
    {
        return $this->getVersion() === self::IP_V4
            ? self::IP_V4_MAX_PREFIX_LENGTH
            : self::IP_V6_MAX_PREFIX_LENGTH;
    }

    public function getOctetsCount(): int
    {
        return $this->getVersion() === self::IP_V4
            ? self::IP_V4_OCTETS
            : self::IP_V6_OCTETS;
    }

    public function getReversePointer(): string
    {
        if ($this->getVersion() === self::IP_V4) {
            $reverseOctets = array_reverse(explode('.', $this->__toString()));

            return implode('.', $reverseOctets).'.in-addr.arpa';
        }
        $unpacked = unpack('H*hex', $this->in_addr);
        if ($unpacked === false || ! isset($unpacked['hex'])) {
            throw new IpException('Unable to unpack IP address');
        }
        /** @var array{hex: string} $unpacked */
        $reverseOctets = array_reverse(mb_str_split($unpacked['hex']));

        return implode('.', $reverseOctets).'.ip6.arpa';
    }

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
            $binary[] = mb_str_pad(decbin($char), 8, '0', STR_PAD_LEFT);
        }

        return implode('', $binary);
    }

    public function toHex(): string
    {
        return bin2hex($this->in_addr);
    }

    public function toLong(): string
    {
        $long = 0;
        if ($this->getVersion() === self::IP_V4) {
            $ip = inet_ntop($this->in_addr);
            if ($ip === false) {
                throw new IpException('Unable to unpack IP address');
            }
            $longValue = ip2long($ip);
            if ($longValue === false) {
                throw new IpException('Unable to convert IP address');
            }
            $long = sprintf('%u', $longValue);
        } else {
            $long = '0';
            $octet = self::IP_V6_OCTETS - 1;
            $chars = unpack('C*', $this->in_addr);
            if ($chars === false) {
                throw new IpException('Unable to unpack IP address');
            }
            /** @var array<int, int> $chars */
            foreach ($chars as $char) {
                $long = bcadd($long, bcmul((string) $char, bcpow('256', (string) $octet--)));
            }
        }

        return (string) $long;
    }

    /**
     * @param  int  $to
     *
     * @throws IpException
     */
    public function next($to = 1): self
    {
        if ($to < 0) {
            throw new IpException('Number must be greater than 0');
        }

        $unpacked = unpack('C*', $this->in_addr);
        if ($unpacked === false) {
            throw new IpException('Unable to unpack IP address');
        }
        /** @var array<int, int> $unpacked */
        for ($i = 0; $i < $to; $i++) {
            for ($byte = count($unpacked); $byte >= 1; $byte--) {
                if ($unpacked[$byte] < 255) {
                    $unpacked[$byte]++;
                    break;
                }

                $unpacked[$byte] = 0;
            }
        }

        $ip = inet_ntop(pack('C*', ...$unpacked));
        if ($ip === false) {
            throw new IpException('Invalid IP address format');
        }

        return new self($ip);
    }

    /**
     * @param  int  $to
     *
     * @throws IpException
     */
    public function prev($to = 1): self
    {

        if ($to < 0) {
            throw new IpException('Number must be greater than 0');
        }

        $unpacked = unpack('C*', $this->in_addr);
        if ($unpacked === false) {
            throw new IpException('Unable to unpack IP address');
        }
        /** @var array<int, int> $unpacked */
        for ($i = 0; $i < $to; $i++) {
            for ($byte = count($unpacked); $byte >= 1; $byte--) {
                if ($unpacked[$byte] === 0) {
                    $unpacked[$byte] = 255;
                } else {
                    $unpacked[$byte]--;
                    break;
                }
            }
        }

        $ip = inet_ntop(pack('C*', ...$unpacked));
        if ($ip === false) {
            throw new IpException('Invalid IP address format');
        }

        return new self($ip);
    }
}
