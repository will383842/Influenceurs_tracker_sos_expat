<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PressRelease extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'parent_id', 'title', 'slug', 'content_html', 'excerpt',
        'meta_title', 'meta_description',
        'keyword_primary', 'language', 'country', 'tone',
        'json_ld', 'hreflang_map',
        'seo_score', 'quality_score', 'generation_cost_cents',
        'ai_model', 'status',
        'published_at', 'published_url', 'canonical_url',
        'created_by',
    ];

    protected $casts = [
        'json_ld'               => 'array',
        'hreflang_map'          => 'array',
        'seo_score'             => 'integer',
        'quality_score'         => 'integer',
        'generation_cost_cents' => 'integer',
        'published_at'          => 'datetime',
    ];

    // ============================================================
    // Boot
    // ============================================================

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->uuid)) {
                $model->uuid = (string) Str::uuid();
            }
        });
    }

    // ============================================================
    // Relationships
    // ============================================================

    public function translations(): HasMany
    {
        return $this->hasMany(PressRelease::class, 'parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(PressRelease::class, 'parent_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function generationLogs(): MorphMany
    {
        return $this->morphMany(GenerationLog::class, 'loggable');
    }

    public function seoAnalysis(): MorphOne
    {
        return $this->morphOne(SeoAnalysis::class, 'analyzable')->latestOfMany();
    }

    public function publicationQueue(): MorphMany
    {
        return $this->morphMany(PublicationQueueItem::class, 'publishable');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', 'published')->whereNotNull('published_at');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', 'draft');
    }

    public function scopeLanguage(Builder $query, string $lang): Builder
    {
        return $query->where('language', $lang);
    }
}
