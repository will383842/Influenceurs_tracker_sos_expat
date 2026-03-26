<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublicationSchedule extends Model
{
    protected $fillable = [
        'endpoint_id', 'max_per_day', 'max_per_hour', 'min_interval_minutes',
        'active_hours_start', 'active_hours_end', 'active_days',
        'auto_pause_on_errors', 'is_active',
    ];

    protected $casts = [
        'active_days'          => 'array',
        'is_active'            => 'boolean',
        'max_per_day'          => 'integer',
        'max_per_hour'         => 'integer',
        'min_interval_minutes' => 'integer',
        'auto_pause_on_errors' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(PublishingEndpoint::class, 'endpoint_id');
    }
}
