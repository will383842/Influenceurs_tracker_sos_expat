<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentGenerated extends Model
{
    protected $table = 'content_generated';

    protected $fillable = [
        'source_article_id', 'country_id',
        'title', 'slug', 'content_text', 'content_html',
        'external_links_used', 'ai_model', 'ai_tokens_used',
        'status', 'published_at', 'published_url',
    ];

    protected $casts = [
        'external_links_used' => 'array',
        'ai_tokens_used'      => 'integer',
        'published_at'        => 'datetime',
    ];

    public function sourceArticle()
    {
        return $this->belongsTo(ContentArticle::class, 'source_article_id');
    }

    public function country()
    {
        return $this->belongsTo(ContentCountry::class, 'country_id');
    }
}
