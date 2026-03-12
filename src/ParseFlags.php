<?php

declare(strict_types=1);

namespace IPTools;

final class ParseFlags
{
    public const STRICT = 0;

    public const ALLOW_PORT = 1 << 0;

    public const ALLOW_ZONE_ID = 1 << 1;

    public const ALLOW_NON_DECIMAL_IPV4 = 1 << 2;

    public const ALLOW_NON_QUAD_IPV4 = 1 << 3;

    public const ALLOW_WILDCARDS = 1 << 4;

    public const DEFAULT = self::ALLOW_PORT
        | self::ALLOW_ZONE_ID
        | self::ALLOW_NON_DECIMAL_IPV4
        | self::ALLOW_WILDCARDS;

    private function __construct() {}
}
