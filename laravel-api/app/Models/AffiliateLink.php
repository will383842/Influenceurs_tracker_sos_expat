<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AffiliateLink extends Model
{
    protected $fillable = [
        'article_type', 'article_id', 'url', 'anchor_text', 'program', 'position',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function article(): MorphTo
    {
        return $this->morphTo();
    }
}
