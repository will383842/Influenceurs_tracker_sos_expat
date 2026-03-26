<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromptTemplate extends Model
{
    protected $fillable = [
        'name', 'description', 'content_type', 'phase',
        'system_message', 'user_message_template',
        'model', 'temperature', 'max_tokens',
        'is_active', 'version', 'created_by',
    ];

    protected $casts = [
        'temperature' => 'decimal:2',
        'max_tokens'  => 'integer',
        'version'     => 'integer',
        'is_active'   => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeForPhase(Builder $query, string $contentType, string $phase): Builder
    {
        return $query->where('content_type', $contentType)
            ->where('phase', $phase)
            ->where('is_active', true)
            ->orderByDesc('version');
    }
}
