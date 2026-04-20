<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Option D — P1 : Modèle des flux RSS de blogs (cible du scraping d'auteurs).
 *
 * Distinct de RssFeed (news). Alimente influenceurs avec contact_type=blog.
 */
class RssBlogFeed extends Model
{
    protected $fillable = [
        'name', 'url', 'base_url',
        'language', 'country', 'category',
        'active', 'fetch_about', 'fetch_pattern_inference',
        'fetch_interval_hours',
        'last_scraped_at', 'last_contacts_found', 'total_contacts_found',
        'about_emails', 'about_fetched_at',
        'last_error', 'notes',
    ];

    protected $casts = [
        'active'                  => 'boolean',
        'fetch_about'             => 'boolean',
        'fetch_pattern_inference' => 'boolean',
        'fetch_interval_hours'    => 'integer',
        'last_contacts_found'     => 'integer',
        'total_contacts_found'    => 'integer',
        'about_emails'            => 'array',
        'last_scraped_at'         => 'datetime',
        'about_fetched_at'        => 'datetime',
    ];

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    /**
     * Feeds dus pour un nouveau scrape : jamais scrapés ou intervalle écoulé.
     */
    public function scopeDue(Builder $q): Builder
    {
        return $q->where(function ($sub) {
            $sub->whereNull('last_scraped_at')
                ->orWhereRaw('last_scraped_at < NOW() - (fetch_interval_hours || \' hours\')::interval');
        });
    }

    /**
     * Retourne true si le cache about_emails est valide (< 7 jours).
     */
    public function hasValidAboutCache(): bool
    {
        return $this->about_fetched_at !== null
            && $this->about_fetched_at->isAfter(now()->subDays(7));
    }

    /**
     * Déduit base_url depuis url du feed si non renseigné.
     */
    public function resolvedBaseUrl(): ?string
    {
        if (!empty($this->base_url)) {
            return rtrim($this->base_url, '/');
        }
        $parts = parse_url($this->url);
        if (!$parts || empty($parts['host'])) {
            return null;
        }
        $scheme = $parts['scheme'] ?? 'https';
        return "{$scheme}://{$parts['host']}";
    }
}
