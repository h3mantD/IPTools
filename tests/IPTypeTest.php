<?php

declare(strict_types=1);

use IPTools\IP;
use IPTools\IPType;
use IPTools\TypeRegistry;
use PHPUnit\Framework\TestCase;

final class IPTypeTest extends TestCase
{
    // -------------------------------------------------------------------------
    // IPType enum
    // -------------------------------------------------------------------------

    public function test_enum_values(): void
    {
        $this->assertSame('private', IPType::PRIVATE->value);
        $this->assertSame('global', IPType::GLOBAL->value);
        $this->assertSame('unique-local', IPType::UNIQUE_LOCAL->value);
        $this->assertSame('link-local', IPType::LINK_LOCAL->value);
    }

    // -------------------------------------------------------------------------
    // IPv4 classification
    // -------------------------------------------------------------------------

    public function test_ipv4_unspecified(): void
    {
        $ip = new IP('0.0.0.0');
        $this->assertSame(IPType::UNSPECIFIED, $ip->primaryType());
        $this->assertTrue($ip->is(IPType::UNSPECIFIED));
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv4_loopback(): void
    {
        $ip = new IP('127.0.0.1');
        $this->assertSame(IPType::LOOPBACK, $ip->primaryType());
        $this->assertTrue($ip->isLoopback());
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv4_broadcast(): void
    {
        $ip = new IP('255.255.255.255');
        $this->assertSame(IPType::BROADCAST, $ip->primaryType());
        $this->assertTrue($ip->is(IPType::BROADCAST));
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv4_documentation(): void
    {
        $ip = new IP('192.0.2.1');
        $this->assertSame(IPType::DOCUMENTATION, $ip->primaryType());
        $this->assertTrue($ip->isDocumentation());
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv4_benchmarking(): void
    {
        $ip = new IP('198.18.0.1');
        $this->assertSame(IPType::BENCHMARKING, $ip->primaryType());
        $this->assertTrue($ip->is(IPType::BENCHMARKING));
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv4_cgnat(): void
    {
        $ip = new IP('100.64.0.1');
        $this->assertSame(IPType::CGNAT, $ip->primaryType());
        $this->assertTrue($ip->is(IPType::CGNAT));
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv4_global(): void
    {
        $ip = new IP('8.8.8.8');
        $this->assertSame(IPType::GLOBAL, $ip->primaryType());
        $this->assertTrue($ip->isGlobalRoutable());
        $this->assertFalse($ip->isPrivate());
    }

    public function test_ipv4_ietf_anycast_addresses_are_global(): void
    {
        $pcp = new IP('192.0.0.9');
        $turn = new IP('192.0.0.10');
        $special = new IP('192.0.0.11');

        $this->assertSame(IPType::GLOBAL, $pcp->primaryType());
        $this->assertSame(IPType::GLOBAL, $turn->primaryType());
        $this->assertSame(IPType::RESERVED, $special->primaryType());
        $this->assertTrue($pcp->isGlobalRoutable());
        $this->assertTrue($turn->isGlobalRoutable());
        $this->assertFalse($special->isGlobalRoutable());
    }

    public function test_ipv4_private10(): void
    {
        $ip = new IP('10.0.0.1');
        $this->assertSame(IPType::PRIVATE, $ip->primaryType());
        $this->assertTrue($ip->isPrivate());
    }

    public function test_ipv4_private172(): void
    {
        $ip = new IP('172.16.0.1');
        $this->assertSame(IPType::PRIVATE, $ip->primaryType());
        $this->assertTrue($ip->isPrivate());
    }

    public function test_ipv4_private192(): void
    {
        $ip = new IP('192.168.1.1');
        $this->assertSame(IPType::PRIVATE, $ip->primaryType());
        $this->assertTrue($ip->isPrivate());
    }

    public function test_ipv4_link_local(): void
    {
        $ip = new IP('169.254.1.1');
        $this->assertSame(IPType::LINK_LOCAL, $ip->primaryType());
        $this->assertTrue($ip->isLinkLocal());
    }

    public function test_ipv4_multicast(): void
    {
        $ip = new IP('224.0.0.1');
        $this->assertSame(IPType::MULTICAST, $ip->primaryType());
        $this->assertTrue($ip->isMulticast());
    }

    public function test_ipv4_documentation_test2(): void
    {
        $ip = new IP('198.51.100.1');
        $this->assertSame(IPType::DOCUMENTATION, $ip->primaryType());
        $this->assertTrue($ip->isDocumentation());
    }

    public function test_ipv4_documentation_test3(): void
    {
        $ip = new IP('203.0.113.1');
        $this->assertSame(IPType::DOCUMENTATION, $ip->primaryType());
        $this->assertTrue($ip->isDocumentation());
    }

    // -------------------------------------------------------------------------
    // IPv6 classification
    // -------------------------------------------------------------------------

    public function test_ipv6_unspecified(): void
    {
        $ip = new IP('::');
        $this->assertSame(IPType::UNSPECIFIED, $ip->primaryType());
        $this->assertTrue($ip->is(IPType::UNSPECIFIED));
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv6_loopback(): void
    {
        $ip = new IP('::1');
        $this->assertSame(IPType::LOOPBACK, $ip->primaryType());
        $this->assertTrue($ip->isLoopback());
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv6_link_local(): void
    {
        $ip = new IP('fe80::1');
        $this->assertSame(IPType::LINK_LOCAL, $ip->primaryType());
        $this->assertTrue($ip->isLinkLocal());
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv6_unique_local(): void
    {
        $ip = new IP('fc00::1');
        $this->assertSame(IPType::UNIQUE_LOCAL, $ip->primaryType());
        $this->assertTrue($ip->isPrivate());
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv6_unique_local_fd(): void
    {
        $ip = new IP('fd00::1');
        $this->assertSame(IPType::UNIQUE_LOCAL, $ip->primaryType());
        $this->assertTrue($ip->isPrivate());
    }

    public function test_ipv6_documentation(): void
    {
        $ip = new IP('2001:db8::1');
        $this->assertSame(IPType::DOCUMENTATION, $ip->primaryType());
        $this->assertTrue($ip->isDocumentation());
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv6_multicast(): void
    {
        $ip = new IP('ff02::1');
        $this->assertSame(IPType::MULTICAST, $ip->primaryType());
        $this->assertTrue($ip->isMulticast());
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv6_global(): void
    {
        $ip = new IP('2606:4700:4700::1111');
        $this->assertSame(IPType::GLOBAL, $ip->primaryType());
        $this->assertTrue($ip->isGlobalRoutable());
        $this->assertFalse($ip->isPrivate());
    }

    public function test_ipv6_benchmarking(): void
    {
        $ip = new IP('2001:2::1');
        $this->assertSame(IPType::BENCHMARKING, $ip->primaryType());
        $this->assertTrue($ip->is(IPType::BENCHMARKING));
        $this->assertFalse($ip->isGlobalRoutable());
    }

    public function test_ipv6_orchid_v2_is_reserved(): void
    {
        $ip = new IP('2001:20::1');
        $this->assertSame(IPType::RESERVED, $ip->primaryType());
        $this->assertTrue($ip->isReserved());
        $this->assertFalse($ip->isGlobalRoutable());
    }

    // -------------------------------------------------------------------------
    // types() returns multiple matching types
    // -------------------------------------------------------------------------

    public function test_types_returns_multiple_for_mcast_documentation(): void
    {
        // 233.252.0.1 is in both MULTICAST (224.0.0.0/4) and DOCUMENTATION (233.252.0.0/24)
        $ip = new IP('233.252.0.1');
        $types = $ip->types();
        $this->assertContains(IPType::MULTICAST, $types);
        $this->assertContains(IPType::DOCUMENTATION, $types);
        // MULTICAST has higher precedence
        $this->assertSame(IPType::MULTICAST, $ip->primaryType());
    }

    public function test_types_global_returns_single_global(): void
    {
        $ip = new IP('8.8.8.8');
        $this->assertSame([IPType::GLOBAL], $ip->types());
    }

    // -------------------------------------------------------------------------
    // TypeRegistry precedence ordering
    // -------------------------------------------------------------------------

    public function test_precedence_order_matches_spec(): void
    {
        $precedence = TypeRegistry::PRECEDENCE;
        $this->assertSame(IPType::UNSPECIFIED, $precedence[0]);
        $this->assertSame(IPType::LOOPBACK, $precedence[1]);
        $this->assertSame(IPType::BROADCAST, $precedence[2]);
        $this->assertSame(IPType::GLOBAL, $precedence[array_key_last($precedence)]);
    }

    // -------------------------------------------------------------------------
    // is() helper
    // -------------------------------------------------------------------------

    public function test_is_helper_works(): void
    {
        $ip = new IP('127.0.0.1');
        $this->assertTrue($ip->is(IPType::LOOPBACK));
        $this->assertFalse($ip->is(IPType::PRIVATE));
        $this->assertFalse($ip->is(IPType::GLOBAL));
    }

    // -------------------------------------------------------------------------
    // isReserved() convenience method
    // -------------------------------------------------------------------------

    public function test_ipv4_is_reserved(): void
    {
        $this->assertTrue((new IP('240.0.0.1'))->isReserved());
        $this->assertFalse((new IP('8.8.8.8'))->isReserved());
        $this->assertFalse((new IP('10.0.0.1'))->isReserved());
    }

    public function test_ipv6_is_reserved(): void
    {
        $this->assertTrue((new IP('2002::1'))->isReserved());   // 6to4
        $this->assertFalse((new IP('fc00::1'))->isReserved());  // unique-local, not reserved
        $this->assertFalse((new IP('2606:4700:4700::1111'))->isReserved());
    }
}
