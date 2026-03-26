<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedArticleFaq extends Model
{
    protected $fillable = [
        'article_id', 'question', 'answer', 'sort_order',
    ];

    protected $casts = [
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
