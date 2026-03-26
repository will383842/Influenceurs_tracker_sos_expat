<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PressDossierItem extends Model
{
    protected $fillable = [
        'dossier_id', 'itemable_type', 'itemable_id', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function dossier(): BelongsTo
    {
        return $this->belongsTo(PressDossier::class, 'dossier_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
