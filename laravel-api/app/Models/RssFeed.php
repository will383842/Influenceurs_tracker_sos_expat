<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RssFeed extends Model
{
    protected $fillable = [
        'name',
        'url',
        'language',
        'country',
        'category',
        'active',
        'fetch_interval_hours',
        'last_fetched_at',
        'items_fetched_count',
        'relevance_threshold',
        'notes',
    ];

    protected $casts = [
        'active'               => 'boolean',
        'last_fetched_at'      => 'datetime',
        'fetch_interval_hours' => 'integer',
        'relevance_threshold'  => 'integer',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(RssFeedItem::class, 'feed_id');
    }

    public function pendingItems(): HasMany
    {
        return $this->hasMany(RssFeedItem::class, 'feed_id')->where('status', 'pending');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
