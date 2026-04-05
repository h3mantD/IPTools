<?php

declare(strict_types=1);

namespace IPTools\Exception;

use Exception;

/**
 * Thrown for IP range-related errors.
 *
 * Examples: first IP greater than last, version mismatch between
 * range endpoints, iterator position out of bounds.
 */
class RangeException extends Exception implements IpToolsException {}
