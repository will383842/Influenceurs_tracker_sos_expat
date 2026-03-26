<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ApiCost extends Model
{
    protected $fillable = [
        'service', 'model', 'operation',
        'input_tokens', 'output_tokens', 'cost_cents',
        'costable_type', 'costable_id', 'created_by',
    ];

    protected $casts = [
        'input_tokens'  => 'integer',
        'output_tokens' => 'integer',
        'cost_cents'    => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function costable(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
