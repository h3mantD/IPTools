<?php

declare(strict_types=1);

namespace IPTools;

enum IPType: string
{
    case UNSPECIFIED = 'unspecified';
    case LOOPBACK = 'loopback';
    case BROADCAST = 'broadcast';
    case MULTICAST = 'multicast';
    case LINK_LOCAL = 'link-local';
    case DOCUMENTATION = 'documentation';
    case BENCHMARKING = 'benchmarking';
    case CGNAT = 'cgnat';
    case PRIVATE = 'private';
    case UNIQUE_LOCAL = 'unique-local';
    case RESERVED = 'reserved';
    case GLOBAL = 'global';
}
