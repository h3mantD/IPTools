<?php

declare(strict_types=1);

namespace IPTools\Exception;

/**
 * Marker interface for all IPTools exceptions.
 *
 * Allows callers to catch every library-specific exception with a single
 * `catch (IpToolsException $e)` while still distinguishing between
 * IP, Network, and Range errors when needed.
 */
interface IpToolsException {}
