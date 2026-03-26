<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DailyContentSchedule extends Model
{
    protected $fillable = [
        'name',
        'is_active',
        'pillar_articles_per_day',
        'normal_articles_per_day',
        'qa_per_day',
        'comparatives_per_day',
        'custom_titles',
        'publish_per_day',
        'publish_start_hour',
        'publish_end_hour',
        'publish_irregular',
        'target_country',
        'target_category',
        'min_quality_score',
        'created_by',
    ];

    protected $casts = [
        'custom_titles'            => 'array',
        'is_active'                => 'boolean',
        'publish_irregular'        => 'boolean',
        'pillar_articles_per_day'  => 'integer',
        'normal_articles_per_day'  => 'integer',
        'qa_per_day'               => 'integer',
        'comparatives_per_day'     => 'integer',
        'publish_per_day'          => 'integer',
        'publish_start_hour'       => 'integer',
        'publish_end_hour'         => 'integer',
        'min_quality_score'        => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function logs(): HasMany
    {
        return $this->hasMany(DailyContentLog::class, 'schedule_id');
    }

    public function todayLog(): HasOne
    {
        return $this->hasOne(DailyContentLog::class, 'schedule_id')
            ->where('date', today()->toDateString());
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
