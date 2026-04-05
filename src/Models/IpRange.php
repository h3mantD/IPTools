<?php

declare(strict_types=1);

namespace IPTools\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model for the ip_ranges table.
 *
 * Provided as an optional convenience — SqlRangeStorage operates directly
 * via PDO and does not require this model. Useful when you want to use
 * Eloquent relationships or scopes alongside the storage layer.
 */
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

        // Resolve table name from config at runtime, allowing users to customize it
        $configuredTable = function_exists('config') ? config('iptools.storage.table') : null;
        $this->table = is_string($configuredTable) && $configuredTable !== ''
            ? $configuredTable
            : 'ip_ranges';
    }
}
