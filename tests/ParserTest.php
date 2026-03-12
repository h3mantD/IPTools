<?php

declare(strict_types=1);

use IPTools\Network;
use IPTools\ParsedAddress;
use IPTools\ParseFlags;
use IPTools\Parser;
use IPTools\Range;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function test_ip_with_bracketed_ipv6_port(): void
    {
        $parsed = Parser::ip('[2001:db8::1]:443');

        $this->assertInstanceOf(ParsedAddress::class, $parsed);
        $this->assertSame('2001:db8::1', (string) $parsed->ip);
        $this->assertSame(443, $parsed->port);
        $this->assertNull($parsed->zoneId);
    }

    public function test_ip_with_zone_id(): void
    {
        $parsed = Parser::ip('fe80::1%eth0');

        $this->assertSame('fe80::1', (string) $parsed->ip);
        $this->assertSame('eth0', $parsed->zoneId);
    }

    public function test_zone_id_on_ipv4_throws(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Zone identifiers are only valid for IPv6 addresses');

        Parser::ip('192.168.1.1%eth0');
    }

    public function test_non_decimal_ipv4_hex_bin_octal(): void
    {
        $this->assertSame('10.0.0.1', (string) Parser::ip('0x0a000001')->ip);
        $this->assertSame('10.0.0.1', (string) Parser::ip('0b00001010000000000000000000000001')->ip);
        $this->assertSame('10.0.0.1', (string) Parser::ip('0o1200000001')->ip);
    }

    public function test_strict_mode_disables_port_and_wildcards(): void
    {
        $this->expectException(Exception::class);
        Parser::ip('1.2.3.4:80', ParseFlags::STRICT);
    }

    public function test_range_wildcard_aligned_returns_network(): void
    {
        $result = Parser::range('192.168.*.*');

        $this->assertInstanceOf(Network::class, $result);
        $this->assertSame('192.168.0.0/16', (string) $result);
    }

    public function test_range_wildcard_non_aligned_returns_range(): void
    {
        $result = Parser::range('192.*.1.*');

        $this->assertInstanceOf(Range::class, $result);
        $this->assertSame('192.0.1.0', (string) $result->getFirstIP());
        $this->assertSame('192.255.1.255', (string) $result->getLastIP());
    }

    public function test_any_detects_output_type(): void
    {
        $this->assertInstanceOf(ParsedAddress::class, Parser::any('[2001:db8::1]:443'));
        $this->assertInstanceOf(Network::class, Parser::any('10.0.0.0/24'));
        $this->assertInstanceOf(Range::class, Parser::any('10.0.0.1-10.0.0.3'));
    }
}
