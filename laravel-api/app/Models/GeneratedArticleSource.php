<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedArticleSource extends Model
{
    protected $fillable = [
        'article_id', 'url', 'title', 'excerpt', 'domain', 'trust_score',
    ];

    protected $casts = [
        'trust_score' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'article_id');
    }
}
