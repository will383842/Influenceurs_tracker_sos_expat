<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContentGenerationCampaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'description', 'campaign_type', 'config', 'status',
        'total_items', 'completed_items', 'failed_items', 'total_cost_cents',
        'started_at', 'completed_at', 'created_by',
    ];

    protected $casts = [
        'config'           => 'array',
        'total_items'      => 'integer',
        'completed_items'  => 'integer',
        'failed_items'     => 'integer',
        'total_cost_cents' => 'integer',
        'started_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function items(): HasMany
    {
        return $this->hasMany(ContentCampaignItem::class, 'campaign_id')->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getProgressPercentAttribute(): int
    {
        if ($this->total_items > 0) {
            return (int) round(($this->completed_items / $this->total_items) * 100);
        }

        return 0;
    }
}
