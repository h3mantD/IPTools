<?php

declare(strict_types=1);

namespace IPTools;

use InvalidArgumentException;

final readonly class ParsedAddress
{
    public function __construct(
        public IP $ip,
        public ?int $port = null,
        public ?string $zoneId = null,
    ) {
        if ($this->port !== null && ($this->port < 0 || $this->port > 65535)) {
            throw new InvalidArgumentException('port must be null or between 0 and 65535');
        }
    }
}
