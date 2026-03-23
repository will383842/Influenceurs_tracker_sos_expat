<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Directory extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'url', 'domain', 'category', 'country', 'language',
        'status', 'contacts_extracted', 'contacts_created', 'pages_scraped',
        'last_scraped_at', 'cooldown_until', 'metadata', 'notes', 'created_by',
    ];

    protected $casts = [
        'metadata'       => 'array',
        'last_scraped_at' => 'datetime',
        'cooldown_until' => 'datetime',
    ];

    /**
     * Contacts extracted from this directory.
     */
    public function influenceurs()
    {
        return $this->hasMany(Influenceur::class, 'source', 'domain')
            ->where('source', 'like', 'directory:%');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if this directory is on cooldown (anti-ban protection).
     */
    public function isOnCooldown(): bool
    {
        return $this->cooldown_until && $this->cooldown_until->isFuture();
    }

    /**
     * Extract domain from URL.
     */
    public static function extractDomain(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST) ?? $url;
        return strtolower(preg_replace('/^www\./', '', $host));
    }
}
