<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InternalLink extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'target_type', 'target_id',
        'anchor_text', 'context_sentence', 'is_auto_generated',
    ];

    protected $casts = [
        'is_auto_generated' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function target(): MorphTo
    {
        return $this->morphTo();
    }
}
