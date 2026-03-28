<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PressPublication extends Model
{
    protected $fillable = [
        'name', 'slug', 'base_url', 'team_url', 'contact_url',
        'authors_url', 'articles_url', 'email_pattern', 'email_domain',
        'media_type', 'category', 'topics', 'language', 'country',
        'contacts_count', 'authors_discovered', 'emails_inferred', 'emails_verified',
        'status', 'last_error', 'last_scraped_at',
    ];

    protected $casts = [
        'topics'          => 'array',
        'last_scraped_at' => 'datetime',
    ];

    public function contacts(): HasMany
    {
        return $this->hasMany(PressContact::class, 'publication_id');
    }
}
