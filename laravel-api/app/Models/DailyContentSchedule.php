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
        'total_articles_per_day',
        'taxonomy_distribution',
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
        'taxonomy_distribution'    => 'array',
        'is_active'                => 'boolean',
        'publish_irregular'        => 'boolean',
        'pillar_articles_per_day'  => 'integer',
        'normal_articles_per_day'  => 'integer',
        'qa_per_day'               => 'integer',
        'comparatives_per_day'     => 'integer',
        'total_articles_per_day'   => 'integer',
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

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Calculate content counts from taxonomy_distribution percentages.
     * Falls back to fixed per-day fields when taxonomy_distribution is not set.
     *
     * @return array{pillar: int, normal: int, qa: int, comparatives: int, news: int}
     */
    public function getCalculatedCounts(): array
    {
        $distribution = $this->taxonomy_distribution;
        $total = $this->total_articles_per_day;

        // If no distribution configured, use legacy fixed fields
        if (empty($distribution) || !$total || $total <= 0) {
            return [
                'pillar'       => $this->pillar_articles_per_day ?? 2,
                'normal'       => $this->normal_articles_per_day ?? 5,
                'qa'           => $this->qa_per_day ?? 10,
                'comparatives' => $this->comparatives_per_day ?? 2,
                'news'         => 2,
            ];
        }

        // Map content_type => count using percentages
        $counts = [
            'pillar'       => 0,
            'normal'       => 0,
            'qa'           => 0,
            'comparatives' => 0,
            'news'         => 0,
        ];

        foreach ($distribution as $item) {
            $type = $item['content_type'] ?? null;
            $pct  = $item['percentage'] ?? 0;

            if ($type && isset($counts[$type])) {
                $counts[$type] = (int) round($total * $pct / 100);
            }
        }

        return $counts;
    }
}
