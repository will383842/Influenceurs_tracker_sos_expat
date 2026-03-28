<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Sondage extends Model
{
    protected $fillable = [
        'external_id',
        'title',
        'description',
        'status',
        'language',
        'closes_at',
        'synced_to_blog',
        'last_synced_at',
    ];

    protected $casts = [
        'closes_at'      => 'datetime',
        'last_synced_at' => 'datetime',
        'synced_to_blog' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $sondage) {
            if (empty($sondage->external_id)) {
                $sondage->external_id = (string) Str::uuid();
            }
        });
    }

    public function questions(): HasMany
    {
        return $this->hasMany(SondageQuestion::class)->orderBy('sort_order');
    }
}
