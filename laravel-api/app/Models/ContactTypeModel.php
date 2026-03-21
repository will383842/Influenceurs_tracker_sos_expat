<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class ContactTypeModel extends Model
{
    protected $table = 'contact_types';

    protected $fillable = [
        'value', 'label', 'icon', 'color', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get all active types (cached 10 min).
     */
    public static function allActive(): \Illuminate\Support\Collection
    {
        return Cache::remember('contact_types_active', 600, function () {
            return self::where('is_active', true)
                ->orderBy('sort_order')
                ->get();
        });
    }

    /**
     * Get all type values as flat array (for validation rules).
     */
    public static function validValues(): array
    {
        return self::allActive()->pluck('value')->toArray();
    }

    /**
     * Flush the cache (called after CRUD operations).
     */
    public static function flushCache(): void
    {
        Cache::forget('contact_types_active');
    }
}
