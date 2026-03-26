<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GenerationPreset extends Model
{
    protected $fillable = [
        'name', 'description', 'config', 'content_type', 'is_default', 'created_by',
    ];

    protected $casts = [
        'config'     => 'array',
        'is_default' => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function articles(): HasMany
    {
        return $this->hasMany(GeneratedArticle::class, 'generation_preset_id');
    }
}
