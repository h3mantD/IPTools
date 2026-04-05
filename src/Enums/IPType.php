<?php

declare(strict_types=1);

namespace IPTools\Enums;

/**
 * IANA-defined address type classifications.
 *
 * An IP address may belong to multiple types simultaneously (e.g., an address
 * can be both MULTICAST and DOCUMENTATION). TypeRegistry resolves precedence
 * when a single "primary" type is needed.
 *
 * @see TypeRegistry::classify()  Determines which types apply to a given address.
 * @see https://www.iana.org/assignments/iana-ipv4-special-registry
 * @see https://www.iana.org/assignments/iana-ipv6-special-registry
 */
enum IPType: string
{
    case UNSPECIFIED = 'unspecified';   // 0.0.0.0/8, ::/128
    case LOOPBACK = 'loopback';        // 127.0.0.0/8, ::1/128
    case BROADCAST = 'broadcast';      // 255.255.255.255/32 (IPv4 limited broadcast)
    case MULTICAST = 'multicast';      // 224.0.0.0/4, ff00::/8
    case LINK_LOCAL = 'link-local';    // 169.254.0.0/16, fe80::/10
    case DOCUMENTATION = 'documentation'; // 192.0.2.0/24, 198.51.100.0/24, 203.0.113.0/24, 2001:db8::/32
    case BENCHMARKING = 'benchmarking';   // 198.18.0.0/15, 2001:2::/48
    case CGNAT = 'cgnat';              // 100.64.0.0/10 — Carrier-Grade NAT (RFC 6598)
    case PRIVATE = 'private';          // 10.0.0.0/8, 172.16.0.0/12, 192.168.0.0/16 (RFC 1918)
    case UNIQUE_LOCAL = 'unique-local'; // fc00::/7 — IPv6 equivalent of RFC 1918 private space
    case RESERVED = 'reserved';        // 240.0.0.0/4 and other IANA-reserved blocks
    case GLOBAL = 'global';            // Globally routable unicast (everything not matched above)
}
