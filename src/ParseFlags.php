<?php

declare(strict_types=1);

namespace IPTools;

/**
 * Composable bitflags controlling how Parser interprets input strings.
 *
 * Combine with bitwise OR: `ParseFlags::ALLOW_PORT | ParseFlags::ALLOW_ZONE_ID`.
 * Use STRICT (0) when only standard IP notation should be accepted.
 */
final class ParseFlags
{
    /** Accept only standard IPv4/IPv6 notation — no extras. */
    public const STRICT = 0;

    /** Allow port suffix: `192.168.1.1:8080` or `[::1]:443`. */
    public const ALLOW_PORT = 1 << 0;

    /** Allow IPv6 zone identifiers: `fe80::1%eth0`. */
    public const ALLOW_ZONE_ID = 1 << 1;

    /** Allow hex (0x0a), octal (012), and binary (0b) IPv4 octets. */
    public const ALLOW_NON_DECIMAL_IPV4 = 1 << 2;

    /** Allow 2-part IPv4 like `10.1` (expanded to `10.0.0.1`). Not in DEFAULT — opt-in only. */
    public const ALLOW_NON_QUAD_IPV4 = 1 << 3;

    /** Allow wildcard octets: `192.168.*.*` → range or network. */
    public const ALLOW_WILDCARDS = 1 << 4;

    /**
     * Sensible defaults for general-purpose parsing.
     *
     * Excludes ALLOW_NON_QUAD_IPV4 because the 2-part format is ambiguous
     * and rarely intended in modern input.
     */
    public const DEFAULT = self::ALLOW_PORT
        | self::ALLOW_ZONE_ID
        | self::ALLOW_NON_DECIMAL_IPV4
        | self::ALLOW_WILDCARDS;

    private function __construct() {}
}
