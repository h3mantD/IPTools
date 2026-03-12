<?php

declare(strict_types=1);

namespace IPTools\Models;

use Illuminate\Database\Eloquent\Model;

class IpRange extends Model
{
    /** @var bool */
    public $timestamps = false;

    /** @var string */
    protected $table;

    /** @var array<int, string> */
    protected $guarded = [];

    /** @var array<string, string> */
    protected $casts = [
        'metadata' => 'array',
        'version' => 'integer',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $configuredTable = function_exists('config') ? config('iptools.storage.table') : null;
        $this->table = is_string($configuredTable) && $configuredTable !== ''
            ? $configuredTable
            : 'ip_ranges';
    }
}
