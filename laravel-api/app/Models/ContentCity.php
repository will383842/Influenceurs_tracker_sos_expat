<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentCity extends Model
{
    protected $fillable = [
        'source_id', 'country_id', 'name', 'slug', 'continent',
        'guide_url', 'articles_count', 'scraped_at',
    ];

    protected $casts = [
        'articles_count' => 'integer',
        'scraped_at'     => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }

    public function country()
    {
        return $this->belongsTo(ContentCountry::class, 'country_id');
    }

    public function articles()
    {
        return $this->hasMany(ContentArticle::class, 'city_id');
    }
}
