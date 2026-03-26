<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArticleKeyword extends Model
{
    protected $fillable = [
        'article_id', 'keyword_id',
        'usage_type', 'density_percent',
        'occurrences', 'position_context',
    ];

    protected $casts = [
        'density_percent' => 'decimal:2',
        'occurrences'     => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'article_id');
    }

    public function keyword(): BelongsTo
    {
        return $this->belongsTo(KeywordTracking::class, 'keyword_id');
    }
}
