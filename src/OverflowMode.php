<?php

declare(strict_types=1);

namespace IPTools;

enum OverflowMode: string
{
    case THROW = 'throw';
    case NULL = 'null';
    case WRAP = 'wrap';
    case CLAMP = 'clamp';
}
