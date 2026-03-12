<?php

declare(strict_types=1);

namespace IPTools\Storage;

use InvalidArgumentException;
use IPTools\IP;

final class AddressCodec
{
    public static function to16(IP $ip): string
    {
        if ($ip->getVersion() === IP::IP_V4) {
            return str_repeat("\x00", 12).$ip->inAddr();
        }

        return $ip->inAddr();
    }

    public static function from16(string $bin, int $version): IP
    {
        if (strlen($bin) !== 16) {
            throw new InvalidArgumentException('Address binary value must be exactly 16 bytes');
        }

        if ($version === 4) {
            return IP::parseInAddr(substr($bin, 12, 4));
        }

        if ($version === 6) {
            return IP::parseInAddr($bin);
        }

        throw new InvalidArgumentException('Version must be 4 or 6');
    }
}
