<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentSource extends Model
{
    protected $fillable = [
        'name', 'slug', 'base_url',
        'total_countries', 'total_articles', 'total_links',
        'status', 'last_scraped_at',
    ];

    protected $casts = [
        'total_countries'  => 'integer',
        'total_articles'   => 'integer',
        'total_links'      => 'integer',
        'last_scraped_at'  => 'datetime',
    ];

    public function countries()
    {
        return $this->hasMany(ContentCountry::class, 'source_id');
    }

    public function articles()
    {
        return $this->hasMany(ContentArticle::class, 'source_id');
    }

    public function externalLinks()
    {
        return $this->hasMany(ContentExternalLink::class, 'source_id');
    }

    public function updateStats(): void
    {
        $this->update([
            'total_countries' => $this->countries()->count(),
            'total_articles'  => $this->articles()->count(),
            'total_links'     => $this->externalLinks()->count(),
        ]);
    }
}
