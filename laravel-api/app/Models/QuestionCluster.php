<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionCluster extends Model
{
    protected $fillable = [
        'name', 'slug',
        'country', 'country_slug', 'continent',
        'category', 'language',
        'total_questions', 'total_views', 'total_replies',
        'popularity_score', 'status',
        'generated_article_id', 'generated_qa_count',
        'created_by',
    ];

    protected $casts = [
        'total_questions'    => 'integer',
        'total_views'        => 'integer',
        'total_replies'      => 'integer',
        'popularity_score'   => 'integer',
        'generated_qa_count' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function items(): HasMany
    {
        return $this->hasMany(QuestionClusterItem::class, 'cluster_id');
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(ContentQuestion::class, 'question_cluster_items', 'cluster_id', 'question_id');
    }

    public function generatedArticle(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'generated_article_id');
    }

    public function qaEntries(): HasMany
    {
        return $this->hasMany(QaEntry::class, 'cluster_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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

    public function scopeByCountry(Builder $query, string $country): Builder
    {
        return $query->where('country', $country);
    }

    public function scopeByPopularity(Builder $query): Builder
    {
        return $query->orderByDesc('popularity_score');
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getPrimaryQuestionAttribute(): ?ContentQuestion
    {
        return $this->items()->where('is_primary', true)->first()?->question;
    }
}
