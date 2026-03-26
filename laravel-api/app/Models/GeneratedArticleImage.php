<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedArticleImage extends Model
{
    protected $fillable = [
        'article_id', 'url', 'alt_text', 'source', 'attribution',
        'width', 'height', 'sort_order',
    ];

    protected $casts = [
        'width'      => 'integer',
        'height'     => 'integer',
        'sort_order' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'article_id');
    }
}
