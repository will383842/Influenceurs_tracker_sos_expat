<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TopicCluster extends Model
{
    protected $fillable = [
        'name', 'slug', 'country', 'category',
        'language', 'description',
        'source_articles_count', 'status',
        'keywords_detected',
        'generated_article_id', 'created_by',
    ];

    protected $casts = [
        'keywords_detected'      => 'array',
        'source_articles_count'  => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function sourceArticles(): BelongsToMany
    {
        return $this->belongsToMany(ContentArticle::class, 'topic_cluster_articles', 'cluster_id', 'source_article_id')
            ->withPivot('relevance_score', 'is_primary', 'processing_status', 'extracted_facts')
            ->withTimestamps();
    }

    public function clusterArticles(): HasMany
    {
        return $this->hasMany(TopicClusterArticle::class, 'cluster_id');
    }

    public function generatedArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'generated_article_id');
    }

    public function researchBrief(): HasOne
    {
        return $this->hasOne(ResearchBrief::class, 'cluster_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function qaEntries(): HasMany
    {
        return $this->hasMany(QaEntry::class, 'cluster_id');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', 'ready');
    }

    public function scopeGenerated(Builder $query): Builder
    {
        return $query->where('status', 'generated');
    }
}
