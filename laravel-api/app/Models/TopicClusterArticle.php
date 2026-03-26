<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TopicClusterArticle extends Model
{
    protected $fillable = [
        'cluster_id', 'source_article_id',
        'relevance_score', 'is_primary',
        'processing_status', 'extracted_facts',
    ];

    protected $casts = [
        'extracted_facts'  => 'array',
        'is_primary'       => 'boolean',
        'relevance_score'  => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(TopicCluster::class, 'cluster_id');
    }

    public function sourceArticle(): BelongsTo
    {
        return $this->belongsTo(ContentArticle::class, 'source_article_id');
    }
}
