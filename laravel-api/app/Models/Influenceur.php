<?php

namespace App\Models;

use App\Enums\ContactType;
use App\Enums\PipelineStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Influenceur extends Model
{
    use SoftDeletes;

    /**
     * Contact types that represent organizations with websites (not social profiles).
     * Used to decide whether to store profile_url as website_url for scraping.
     */
    public const NON_SOCIAL_TYPES = [
        // New types
        'consulat', 'association', 'ecole', 'institut_culturel', 'chambre_commerce',
        'presse', 'blog', 'podcast_radio',
        'avocat', 'immobilier', 'assurance', 'banque_fintech', 'traducteur', 'agence_voyage', 'emploi',
        'communaute_expat', 'coworking_coliving', 'logement', 'lieu_communautaire',
        'backlink', 'annuaire', 'plateforme_nomad', 'partenaire',
        // Legacy (for existing data)
        'school', 'press', 'blogger', 'consulats', 'enterprise', 'insurer',
        'travel_agency', 'real_estate', 'translator', 'lawyer', 'partner', 'job_board', 'erasmus',
    ];

    protected $fillable = [
        'contact_type', 'name', 'company', 'position',
        'handle', 'avatar_url', 'platforms', 'primary_platform',
        'followers', 'followers_secondary', 'niche', 'country', 'language', 'timezone',
        'email', 'phone', 'profile_url', 'profile_url_domain', 'website_url',
        'status', 'deal_value_cents', 'deal_probability', 'expected_close_date',
        'assigned_to', 'reminder_days', 'reminder_active', 'last_contact_at',
        'partnership_date', 'notes', 'tags', 'score', 'source', 'created_by',
        'scraped_at', 'scraper_status', 'scraped_emails', 'scraped_phones', 'scraped_social', 'scraped_addresses',
        'email_verified_status', 'email_verified_at', 'quality_score',
    ];

    protected $casts = [
        'contact_type'        => ContactType::class,
        'platforms'           => 'array',
        'followers_secondary' => 'array',
        'tags'                => 'array',
        'reminder_active'     => 'boolean',
        'last_contact_at'     => 'datetime',
        'partnership_date'    => 'date',
        'expected_close_date' => 'date',
        'deal_value_cents'    => 'integer',
        'deal_probability'    => 'integer',
        'score'               => 'integer',
        'scraped_at'          => 'datetime',
        'scraped_emails'      => 'array',
        'scraped_phones'      => 'array',
        'scraped_social'      => 'array',
        'scraped_addresses'   => 'array',
    ];

    public function assignedToUser()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contacts()
    {
        return $this->hasMany(Contact::class)
            ->orderBy('date')
            ->orderBy('created_at');
    }

    public function reminders()
    {
        return $this->hasMany(Reminder::class);
    }

    public function pendingReminder()
    {
        return $this->hasOne(Reminder::class)->where('status', 'pending');
    }

    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function outreachEmails()
    {
        return $this->hasMany(OutreachEmail::class);
    }

    public function outreachSequence()
    {
        return $this->hasOne(OutreachSequence::class);
    }

    /**
     * Scope: influenceurs that count as "valid" for objective progress.
     * Requires: profile_url, name, profile_url_domain, and at least email or phone.
     */
    public function scopeValidForObjective(Builder $query): Builder
    {
        return $query->whereNotNull('profile_url')
            ->whereNotNull('name')
            ->where('name', '!=', '')
            ->whereNotNull('profile_url_domain')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('email')->where('email', '!=', '');
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('phone')->where('phone', '!=', '');
                });
            });
    }
}
