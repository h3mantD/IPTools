<?php

declare(strict_types=1);

namespace IPTools\Exception;

use Exception;

/**
 * Thrown for invalid or malformed individual IP addresses.
 *
 * Examples: invalid format passed to constructor, binary/hex parse failures,
 * out-of-range long values, negative step in next()/previous().
 */
class IpException extends Exception implements IpToolsException {}
