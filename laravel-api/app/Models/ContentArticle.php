<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentArticle extends Model
{
    protected $fillable = [
        'source_id', 'country_id', 'city_id', 'title', 'slug', 'url', 'url_hash',
        'category', 'section', 'content_text', 'content_html',
        'word_count', 'language',
        'external_links', 'ads_and_sponsors', 'images',
        'meta_title', 'meta_description',
        'is_guide', 'scraped_at',
        'processing_status', 'processed_at', 'quality_rating',
    ];

    protected $casts = [
        'word_count'       => 'integer',
        'external_links'   => 'array',
        'ads_and_sponsors' => 'array',
        'images'           => 'array',
        'is_guide'         => 'boolean',
        'scraped_at'       => 'datetime',
        'processed_at'     => 'datetime',
        'quality_rating'   => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function source(): BelongsTo
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(ContentCountry::class, 'country_id');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(ContentCity::class, 'city_id');
    }

    public function links(): HasMany
    {
        return $this->hasMany(ContentExternalLink::class, 'article_id');
    }

    public function clusters(): BelongsToMany
    {
        return $this->belongsToMany(TopicCluster::class, 'topic_cluster_articles', 'source_article_id', 'cluster_id')
            ->withPivot('relevance_score', 'is_primary', 'processing_status', 'extracted_facts')
            ->withTimestamps();
    }
}
