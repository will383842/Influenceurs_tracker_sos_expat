<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranslationBatch extends Model
{
    protected $fillable = [
        'target_language', 'content_type', 'status',
        'total_items', 'completed_items', 'failed_items', 'skipped_items',
        'total_cost_cents', 'current_item_id',
        'error_log',
        'started_at', 'paused_at', 'completed_at',
        'created_by',
    ];

    protected $casts = [
        'error_log'        => 'array',
        'started_at'       => 'datetime',
        'paused_at'        => 'datetime',
        'completed_at'     => 'datetime',
        'total_items'      => 'integer',
        'completed_items'  => 'integer',
        'failed_items'     => 'integer',
        'skipped_items'    => 'integer',
        'total_cost_cents' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getProgressPercentAttribute(): float
    {
        if ($this->total_items === 0) {
            return 0;
        }

        return round(($this->completed_items / $this->total_items) * 100, 1);
    }
}
