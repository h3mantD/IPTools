<?php

declare(strict_types=1);

namespace IPTools;

/**
 * IANA-backed IP type classification registry.
 *
 * Sources:
 *   IPv4: https://www.iana.org/assignments/iana-ipv4-special-registry/iana-ipv4-special-registry.xhtml
 *   IPv6: https://www.iana.org/assignments/iana-ipv6-special-registry/iana-ipv6-special-registry.xhtml
 *
 * Precedence order (for primaryType()):
 *   UNSPECIFIED > LOOPBACK > BROADCAST > MULTICAST > LINK_LOCAL >
 *   DOCUMENTATION > BENCHMARKING > CGNAT > PRIVATE/UNIQUE_LOCAL > RESERVED > GLOBAL
 */
final class TypeRegistry
{
    /**
     * Precedence: lower index = higher priority.
     *
     * @var list<IPType>
     */
    public const PRECEDENCE = [
        IPType::UNSPECIFIED,
        IPType::LOOPBACK,
        IPType::BROADCAST,
        IPType::MULTICAST,
        IPType::LINK_LOCAL,
        IPType::DOCUMENTATION,
        IPType::BENCHMARKING,
        IPType::CGNAT,
        IPType::PRIVATE,
        IPType::UNIQUE_LOCAL,
        IPType::RESERVED,
        IPType::GLOBAL,
    ];

    /** @var array<int, array{type: IPType, network: Network}>|null */
    private static ?array $ipv4Ranges = null;

    /** @var array<int, array{type: IPType, network: Network}>|null */
    private static ?array $ipv6Ranges = null;

    /**
     * @return array<int, array{type: IPType, network: Network}>
     */
    public static function ipv4(): array
    {
        if (self::$ipv4Ranges !== null) {
            return self::$ipv4Ranges;
        }

        self::$ipv4Ranges = self::buildRanges([
            // UNSPECIFIED
            [IPType::UNSPECIFIED, '0.0.0.0/32'],
            // LOOPBACK
            [IPType::LOOPBACK, '127.0.0.0/8'],
            // BROADCAST
            [IPType::BROADCAST, '255.255.255.255/32'],
            // MULTICAST
            [IPType::MULTICAST, '224.0.0.0/4'],
            // LINK_LOCAL
            [IPType::LINK_LOCAL, '169.254.0.0/16'],
            // DOCUMENTATION
            [IPType::DOCUMENTATION, '192.0.2.0/24'],     // TEST-NET-1
            [IPType::DOCUMENTATION, '198.51.100.0/24'],  // TEST-NET-2
            [IPType::DOCUMENTATION, '203.0.113.0/24'],   // TEST-NET-3
            [IPType::DOCUMENTATION, '233.252.0.0/24'],   // MCAST-TEST-NET
            // BENCHMARKING
            [IPType::BENCHMARKING, '198.18.0.0/15'],
            // CGNAT
            [IPType::CGNAT, '100.64.0.0/10'],
            // PRIVATE
            [IPType::PRIVATE, '10.0.0.0/8'],
            [IPType::PRIVATE, '172.16.0.0/12'],
            [IPType::PRIVATE, '192.168.0.0/16'],
            // RESERVED
            [IPType::RESERVED, '0.0.0.0/8'],              // "This" Network
            [IPType::RESERVED, '192.0.0.0/24'],            // IETF Protocol Assignments
            [IPType::RESERVED, '192.88.99.0/24'],          // Formerly 6to4 Relay Anycast
            [IPType::RESERVED, '240.0.0.0/4'],             // Reserved
        ]);

        return self::$ipv4Ranges;
    }

    /**
     * @return array<int, array{type: IPType, network: Network}>
     */
    public static function ipv6(): array
    {
        if (self::$ipv6Ranges !== null) {
            return self::$ipv6Ranges;
        }

        self::$ipv6Ranges = self::buildRanges([
            // UNSPECIFIED
            [IPType::UNSPECIFIED, '::/128'],
            // LOOPBACK
            [IPType::LOOPBACK, '::1/128'],
            // MULTICAST
            [IPType::MULTICAST, 'ff00::/8'],
            // LINK_LOCAL
            [IPType::LINK_LOCAL, 'fe80::/10'],
            // DOCUMENTATION
            [IPType::DOCUMENTATION, '2001:db8::/32'],
            // BENCHMARKING
            [IPType::BENCHMARKING, '2001:2::/48'],
            // UNIQUE_LOCAL
            [IPType::UNIQUE_LOCAL, 'fc00::/7'],
            // RESERVED
            [IPType::RESERVED, '::ffff:0:0/96'],     // IPv4-mapped
            [IPType::RESERVED, '64:ff9b::/96'],       // NAT64 well-known prefix
            [IPType::RESERVED, '64:ff9b:1::/48'],     // NAT64 local-use
            [IPType::RESERVED, '100::/64'],            // Discard-Only
            [IPType::RESERVED, '2001::/32'],           // Teredo
            [IPType::RESERVED, '2001:10::/28'],        // Deprecated ORCHID v1
            [IPType::RESERVED, '2001:20::/28'],        // ORCHID v2
            [IPType::RESERVED, '2002::/16'],           // 6to4
        ]);

        return self::$ipv6Ranges;
    }

    /**
     * Return all matching types for an IP, in precedence order.
     *
     * @return list<IPType>
     */
    public static function classify(IP $ip): array
    {
        $ranges = $ip->getVersion() === IP::IP_V4 ? self::ipv4() : self::ipv6();
        $matched = [];

        foreach ($ranges as ['type' => $type, 'network' => $network]) {
            if (self::ipInNetwork($ip, $network)) {
                $matched[$type->name] = $type;
            }
        }

        if ($matched === []) {
            return [IPType::GLOBAL];
        }

        // Sort by precedence order
        return array_values(array_filter(
            self::PRECEDENCE,
            static fn (IPType $t): bool => isset($matched[$t->name]),
        ));
    }

    private static function ipInNetwork(IP $ip, Network $network): bool
    {
        return strcmp($ip->inAddr(), $network->getFirstIP()->inAddr()) >= 0
            && strcmp($ip->inAddr(), $network->getLastIP()->inAddr()) <= 0;
    }

    /**
     * @param  list<array{0: IPType, 1: string}>  $definitions
     * @return list<array{type: IPType, network: Network}>
     */
    private static function buildRanges(array $definitions): array
    {
        $result = [];
        foreach ($definitions as [$type, $cidr]) {
            $result[] = ['type' => $type, 'network' => Network::parse($cidr)];
        }

        return $result;
    }
}
