<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RssFeedItem extends Model
{
    protected $fillable = [
        'feed_id',
        'guid',
        'title',
        'url',
        'source_name',
        'published_at',
        'original_title',
        'original_excerpt',
        'original_content',
        'language',
        'country',
        'relevance_score',
        'relevance_category',
        'relevance_reason',
        'status',
        'similarity_score',
        'blog_article_uuid',
        'generated_at',
        'error_message',
    ];

    protected $casts = [
        'published_at'    => 'datetime',
        'generated_at'    => 'datetime',
        'relevance_score' => 'integer',
        'similarity_score'=> 'integer',
    ];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(RssFeed::class, 'feed_id');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published');
    }

    public function scopeIrrelevant(Builder $query): Builder
    {
        return $query->where('status', 'irrelevant');
    }
}
