<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentExternalLink extends Model
{
    protected $fillable = [
        'source_id', 'article_id', 'url', 'url_hash', 'original_url',
        'domain', 'anchor_text', 'context', 'country_id',
        'link_type', 'is_affiliate', 'occurrences',
    ];

    protected $casts = [
        'is_affiliate' => 'boolean',
        'occurrences'  => 'integer',
    ];

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }

    public function article()
    {
        return $this->belongsTo(ContentArticle::class, 'article_id');
    }

    public function country()
    {
        return $this->belongsTo(ContentCountry::class, 'country_id');
    }
}
