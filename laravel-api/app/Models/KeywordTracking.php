<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KeywordTracking extends Model
{
    protected $table = 'keyword_tracking';

    protected $fillable = [
        'keyword', 'type', 'language',
        'country', 'category',
        'search_volume_estimate', 'difficulty_estimate',
        'trend', 'articles_using_count',
        'first_used_at',
    ];

    protected $casts = [
        'search_volume_estimate' => 'integer',
        'difficulty_estimate'    => 'integer',
        'articles_using_count'   => 'integer',
        'first_used_at'          => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(GeneratedArticle::class, 'article_keywords', 'keyword_id', 'article_id')
            ->withPivot('usage_type', 'density_percent', 'occurrences', 'position_context')
            ->withTimestamps();
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeByLanguage(Builder $query, string $language): Builder
    {
        return $query->where('language', $language);
    }
}
