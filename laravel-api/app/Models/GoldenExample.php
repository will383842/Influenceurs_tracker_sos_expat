<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoldenExample extends Model
{
    protected $fillable = [
        'article_id', 'criteria', 'score',
    ];

    protected $casts = [
        'criteria' => 'array',
        'score'    => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'article_id');
    }
}
