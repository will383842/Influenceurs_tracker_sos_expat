<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentArticle extends Model
{
    protected $fillable = [
        'source_id', 'country_id', 'title', 'slug', 'url', 'url_hash',
        'category', 'section', 'content_text', 'content_html',
        'word_count', 'language',
        'external_links', 'ads_and_sponsors', 'images',
        'meta_title', 'meta_description',
        'is_guide', 'scraped_at',
    ];

    protected $casts = [
        'word_count'       => 'integer',
        'external_links'   => 'array',
        'ads_and_sponsors' => 'array',
        'images'           => 'array',
        'is_guide'         => 'boolean',
        'scraped_at'       => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }

    public function country()
    {
        return $this->belongsTo(ContentCountry::class, 'country_id');
    }

    public function links()
    {
        return $this->hasMany(ContentExternalLink::class, 'article_id');
    }
}
