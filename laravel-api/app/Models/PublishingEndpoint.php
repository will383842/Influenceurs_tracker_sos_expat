<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PublishingEndpoint extends Model
{
    protected $fillable = [
        'name', 'type', 'config', 'is_active', 'is_default', 'created_by',
    ];

    protected $casts = [
        'config'     => 'array',
        'is_active'  => 'boolean',
        'is_default' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function schedule(): HasOne
    {
        return $this->hasOne(PublicationSchedule::class, 'endpoint_id');
    }

    public function queueItems(): HasMany
    {
        return $this->hasMany(PublicationQueueItem::class, 'endpoint_id');
    }
}
