<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PublicationQueueItem extends Model
{
    protected $table = 'publication_queue';

    protected $fillable = [
        'publishable_type', 'publishable_id', 'endpoint_id',
        'status', 'priority', 'scheduled_at', 'published_at',
        'attempts', 'max_attempts', 'last_error',
        'external_id', 'external_url',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
        'attempts'     => 'integer',
        'max_attempts' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function publishable(): MorphTo
    {
        return $this->morphTo();
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(PublishingEndpoint::class, 'endpoint_id');
    }
}
