<?php

declare(strict_types=1);

use IPTools\IP;
use PHPUnit\Framework\TestCase;

final class IPTest extends TestCase
{
    public static function getTestConstructorExceptionData(): array
    {
        return [
            ['256.0.0.1'],
            ['127.-1.0.1'],
            ['cake'],
            ['12345'],
            ['-12345'],
            ['0000:0000:0000:ffff:0127:0000:0000:0001:0000'],
        ];
    }

    public static function getToStringData(): array
    {
        return [
            ['127.0.0.1', '127.0.0.1'],
            ['2001::', '2001::'],
            ['2001:0000:0000:0000:0000:0000:0000:0000', '2001::'],
            ['2001:0000:0000:0000:8000:0000:0000:0000', '2001::8000:0:0:0'],
        ];
    }

    public static function getTestParseData(): array
    {
        return [
            [2130706433, '127.0.0.1'], // long
            ['0b01111111000000000000000000000001', '127.0.0.1'], // bin
            ['0x7f000001', '127.0.0.1'], // hex,
            ['0x20010000000000008000000000000000', '2001::8000:0:0:0'], // hex
            ['127.0.0.1', '127.0.0.1'],
            ['2001::', '2001::'],
        ];
    }

    public static function getParseBinData(): array
    {
        return [
            [
                '00100000000000010000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000000',
                '2001::',
            ],
            ['01111111000000000000000000000001', '127.0.0.1'],
        ];
    }

    public static function getTestNextData(): array
    {
        return [
            ['192.168.0.1', 1, '192.168.0.2'],
            ['192.168.0.1', 254, '192.168.0.255'],
            ['192.168.0.1', 255, '192.168.1.0'],
            ['2001::', 1, '2001::1'],
            ['2001::', 65535, '2001::ffff'],
            ['2001::', 65536, '2001::1:0'],
        ];
    }

    public static function getTestPrevData(): array
    {
        return [
            ['192.168.1.1', 1, '192.168.1.0'],
            ['192.168.1.0', 1, '192.168.0.255'],
            ['192.168.1.1', 258, '192.167.255.255'],
            ['2001::1', 1, '2001::'],
            ['2001::1:0', 1, '2001::ffff'],
            ['2001::1:0', 65536, '2001::'],
        ];
    }

    public static function getReversePointerData(): array
    {
        return [
            ['192.0.2.5', '5.2.0.192.in-addr.arpa'],
            ['2001:db8::567:89ab', 'b.a.9.8.7.6.5.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa'],
        ];
    }

    public function test_constructor(): void
    {
        $ipv4String = '127.0.0.1';
        $ipv6String = '2001::';

        $ipv4 = new IP($ipv4String);
        $ipv6 = new IP($ipv6String);

        $this->assertEquals(inet_pton($ipv4String), $ipv4->inAddr());
        $this->assertEquals(IP::IP_V4, $ipv4->getVersion());
        $this->assertEquals(IP::IP_V4_MAX_PREFIX_LENGTH, $ipv4->getMaxPrefixLength());
        $this->assertEquals(IP::IP_V4_OCTETS, $ipv4->getOctetsCount());

        $this->assertEquals(inet_pton($ipv6String), $ipv6->inAddr());
        $this->assertEquals(IP::IP_V6, $ipv6->getVersion());
        $this->assertEquals(IP::IP_V6_MAX_PREFIX_LENGTH, $ipv6->getMaxPrefixLength());
        $this->assertEquals(IP::IP_V6_OCTETS, $ipv6->getOctetsCount());
    }

    /**
     * @dataProvider getTestConstructorExceptionData
     */
    public function test_constructor_exception(string $string): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid IP address format');

        new IP($string);
    }

    public function test_constructor_rejects_non_string_types(): void
    {
        $this->expectException(TypeError::class);

        new IP(123.45); // @phpstan-ignore argument.type
    }

    public function test_properties(): void
    {
        $ip = new IP('127.0.0.1');

        $this->assertNotEmpty($ip->maxPrefixLength);
        $this->assertNotEmpty($ip->octetsCount);
        $this->assertNotEmpty($ip->reversePointer);

        $this->assertNotEmpty($ip->bin);
        $this->assertNotEmpty($ip->long);
        $this->assertNotEmpty($ip->hex);
    }

    /**
     * @dataProvider getToStringData
     */
    public function test_to_string(string $actual, string $expected): void
    {
        $ip = new IP($actual);
        $this->assertEquals($expected, (string) $ip);
    }

    /**
     * @dataProvider getTestParseData
     */
    public function test_parse(int|string $ipString, string $expected): void
    {
        $ip = IP::parse($ipString);
        $this->assertEquals($expected, (string) $ip);
    }

    /**
     * @dataProvider getParseBinData
     */
    public function test_parse_bin(string $bin, string $expectedString): void
    {
        $ip = IP::parseBin($bin);

        $this->assertEquals($expectedString, (string) $ip);
        $this->assertEquals($bin, $ip->toBin());
    }

    public function test_parse_bin_exception(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid binary IP address format');

        IP::parseBin('192.168.1.1');
    }

    public function test_parse_long(): void
    {
        $ipv4long = '2130706433';
        $ipv4 = IP::parseLong($ipv4long);

        $ipv6Long = '340277174624079928635746076935438991360';
        $ipv6 = IP::parseLong($ipv6Long, IP::IP_V6);

        $this->assertEquals('127.0.0.1', (string) $ipv4);
        $this->assertEquals($ipv4long, $ipv4->toLong());

        $this->assertEquals('ffff::', (string) $ipv6);
        $this->assertEquals($ipv6Long, $ipv6->toLong());
    }

    public function test_parse_hex(): void
    {
        $hex = '7f000001';
        $ip = IP::parseHex($hex);

        $this->assertEquals('127.0.0.1', (string) $ip);
        $this->assertEquals($hex, $ip->toHex());

    }

    public function test_parse_hex_exception(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Invalid hexadecimal IP address format');

        IP::parseHex('192.168.1.1');
    }

    public function test_parse_in_addr(): void
    {
        $inAddr = inet_pton('127.0.0.1');
        $ip = IP::parseInAddr($inAddr);

        $this->assertEquals($inAddr, $ip->inAddr());

        $inAddr = inet_pton('2001::8000:0:0:0');
        $ip = IP::parseInAddr($inAddr);

        $this->assertEquals($inAddr, $ip->inAddr());
    }

    /**
     * @dataProvider getTestNextData
     */
    public function test_next(string $ip, int $step, string $expected): void
    {
        $object = new IP($ip);
        $next = $object->next($step);

        $this->assertEquals($expected, (string) $next);
    }

    /**
     * @dataProvider getTestPrevData
     */
    public function test_prev(string $ip, int $step, string $expected): void
    {
        $object = new IP($ip);
        $prev = $object->prev($step);

        $this->assertEquals($expected, (string) $prev);
    }

    public function test_prev_exception(): void
    {
        $object = new IP('192.168.1.1');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Number must be non-negative');

        $object->prev(-1);
    }

    /**
     * @dataProvider getReversePointerData
     */
    public function test_reverse_pointer(string $ip, string $expected): void
    {
        $object = new IP($ip);
        $reversePointer = $object->getReversePointer();
        $this->assertEquals($expected, $reversePointer);
    }
}
