<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyContentLog extends Model
{
    protected $fillable = [
        'schedule_id',
        'date',
        'pillar_generated',
        'normal_generated',
        'qa_generated',
        'comparatives_generated',
        'custom_generated',
        'published',
        'total_cost_cents',
        'errors',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'errors'                => 'array',
        'date'                  => 'date',
        'started_at'            => 'datetime',
        'completed_at'          => 'datetime',
        'pillar_generated'      => 'integer',
        'normal_generated'      => 'integer',
        'qa_generated'          => 'integer',
        'comparatives_generated' => 'integer',
        'custom_generated'      => 'integer',
        'published'             => 'integer',
        'total_cost_cents'      => 'integer',
    ];

    protected $appends = ['total_generated'];

    // ============================================================
    // Relationships
    // ============================================================

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(DailyContentSchedule::class, 'schedule_id');
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getTotalGeneratedAttribute(): int
    {
        return $this->pillar_generated
            + $this->normal_generated
            + $this->qa_generated
            + $this->comparatives_generated
            + $this->custom_generated;
    }
}
