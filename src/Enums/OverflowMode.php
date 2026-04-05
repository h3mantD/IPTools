<?php

declare(strict_types=1);

namespace IPTools\Enums;

/**
 * Controls behavior when IP arithmetic exceeds address-space boundaries.
 *
 * Used by IP::addOffset() and IP::shift() to let callers choose how
 * boundary conditions are handled rather than baking in a single strategy.
 */
enum OverflowMode: string
{
    /** Raise an OverflowException (fail-fast, the default). */
    case THROW = 'throw';

    /** Return null — useful for boundary-safe traversal like next()/previous(). */
    case NULL = 'null';

    /** Modulo arithmetic: 255.255.255.255 + 1 wraps to 0.0.0.0. */
    case WRAP = 'wrap';

    /** Saturate at the boundary: 255.255.255.255 + N stays 255.255.255.255. */
    case CLAMP = 'clamp';
}
