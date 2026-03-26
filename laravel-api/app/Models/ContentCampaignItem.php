<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentCampaignItem extends Model
{
    protected $fillable = [
        'campaign_id', 'itemable_type', 'itemable_id',
        'title_hint', 'config_override', 'status', 'error_message',
        'sort_order', 'scheduled_at', 'completed_at',
    ];

    protected $casts = [
        'config_override' => 'array',
        'scheduled_at'    => 'datetime',
        'completed_at'    => 'datetime',
        'sort_order'      => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(ContentGenerationCampaign::class, 'campaign_id');
    }

    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }
}
