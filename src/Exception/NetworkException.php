<?php

declare(strict_types=1);

namespace IPTools\Exception;

use Exception;

/**
 * Thrown for CIDR network-related errors.
 *
 * Examples: invalid prefix length, version mismatch between IP and netmask,
 * exclude target outside network boundaries, invalid netmask format.
 */
class NetworkException extends Exception implements IpToolsException {}
