<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ExternalLinkRegistry extends Model
{
    protected $table = 'external_link_registry';

    protected $fillable = [
        'article_type', 'article_id', 'url', 'domain',
        'anchor_text', 'trust_score', 'is_nofollow',
    ];

    protected $casts = [
        'trust_score'  => 'integer',
        'is_nofollow'  => 'boolean',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function article(): MorphTo
    {
        return $this->morphTo();
    }
}
