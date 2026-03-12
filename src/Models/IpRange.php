<?php

declare(strict_types=1);

namespace IPTools\Models;

use Illuminate\Database\Eloquent\Model;

class IpRange extends Model
{
    /** @var string */
    protected $table = 'ip_ranges';

    /** @var array<int, string> */
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'version' => 'integer',
    ];
}
