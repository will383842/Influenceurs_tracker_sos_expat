<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneratedArticleVersion extends Model
{
    protected $fillable = [
        'article_id', 'version_number', 'content_html',
        'meta_title', 'meta_description', 'changes_summary', 'created_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function article(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'article_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
