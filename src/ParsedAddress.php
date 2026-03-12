<?php

declare(strict_types=1);

namespace IPTools;

final readonly class ParsedAddress
{
    public function __construct(
        public IP $ip,
        public ?int $port = null,
        public ?string $zoneId = null,
    ) {}
}
