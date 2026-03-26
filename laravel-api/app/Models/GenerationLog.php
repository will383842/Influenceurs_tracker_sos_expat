<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GenerationLog extends Model
{
    protected $fillable = [
        'loggable_type', 'loggable_id', 'phase', 'status', 'message',
        'tokens_used', 'cost_cents', 'duration_ms', 'metadata',
    ];

    protected $casts = [
        'tokens_used' => 'integer',
        'cost_cents'  => 'integer',
        'duration_ms' => 'integer',
        'metadata'    => 'array',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function loggable(): MorphTo
    {
        return $this->morphTo();
    }
}
