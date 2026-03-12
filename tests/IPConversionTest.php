<?php

declare(strict_types=1);

use IPTools\IP;
use PHPUnit\Framework\TestCase;

final class IPConversionTest extends TestCase
{
    public function test_ipv4_mapped_roundtrip(): void
    {
        $mapped = IP::toIpv4Mapped(new IP('127.0.0.1'));

        $this->assertSame('00000000000000000000ffff7f000001', $mapped->toHex());
        $this->assertTrue($mapped->isIpv4Mapped());
        $this->assertSame('127.0.0.1', (string) IP::fromIpv4Mapped($mapped));
        $this->assertSame('127.0.0.1', (string) IP::fromIpv4Mapped('::ffff:7f00:1'));
    }

    public function test_from_ipv4_mapped_throws_for_non_mapped(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address is not an IPv4-mapped IPv6 address');

        IP::fromIpv4Mapped('2001:db8::1');
    }

    public function test_to_ipv4_mapped_throws_for_ipv6_input(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Expected an IPv4 address');

        IP::toIpv4Mapped(new IP('::1'));
    }

    public function test_6to4_roundtrip(): void
    {
        $sixToFour = IP::to6to4(new IP('10.0.0.1'));

        $this->assertSame('20020a00000100000000000000000000', $sixToFour->toHex());
        $this->assertTrue($sixToFour->is6to4());
        $this->assertSame('10.0.0.1', (string) IP::from6to4($sixToFour));
        $this->assertSame('10.0.0.1', (string) IP::from6to4('2002:0a00:0001::'));
    }

    public function test_from_6to4_throws_for_non_6to4(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address is not a 6to4 IPv6 address');

        IP::from6to4('2001:db8::1');
    }

    public function test_nat64_roundtrip_default_prefix(): void
    {
        $nat64 = IP::toNat64(new IP('8.8.8.8'));

        $this->assertSame('0064ff9b000000000000000008080808', $nat64->toHex());
        $this->assertTrue($nat64->isNat64());
        $this->assertSame('8.8.8.8', (string) IP::fromNat64($nat64));
        $this->assertSame('8.8.8.8', (string) IP::fromNat64('64:ff9b::808:808'));
    }

    public function test_nat64_with_custom_prefix(): void
    {
        $nat64 = IP::toNat64(new IP('192.0.2.1'), '64:ff9b:1::/96');

        $this->assertTrue($nat64->isNat64('64:ff9b:1::/96'));
        $this->assertSame('192.0.2.1', (string) IP::fromNat64($nat64, '64:ff9b:1::/96'));
    }

    public function test_nat64_invalid_prefix_length_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('NAT64 prefix must be an IPv6 /96 network');

        IP::toNat64(new IP('8.8.8.8'), '64:ff9b::/64');
    }

    public function test_from_nat64_throws_when_outside_prefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Address is not within the NAT64 prefix');

        IP::fromNat64('2001:db8::1');
    }
}
