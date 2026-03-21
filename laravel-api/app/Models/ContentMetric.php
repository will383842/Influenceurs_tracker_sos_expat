<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentMetric extends Model
{
    protected $fillable = [
        'date', 'landing_pages', 'articles', 'indexed_pages',
        'top10_positions', 'position_zero', 'ai_cited',
        'daily_visits', 'calls_generated', 'revenue_cents',
        'search_console_data', 'analytics_data',
    ];

    protected $casts = [
        'date'                 => 'date',
        'search_console_data'  => 'array',
        'analytics_data'       => 'array',
    ];

    /**
     * Get or create today's metrics record.
     */
    public static function today(): self
    {
        return self::firstOrCreate(
            ['date' => now()->toDateString()],
            [
                'landing_pages'   => 0,
                'articles'        => 0,
                'indexed_pages'   => 0,
                'top10_positions' => 0,
                'position_zero'   => 0,
                'ai_cited'        => 0,
                'daily_visits'    => 0,
                'calls_generated' => 0,
                'revenue_cents'   => 0,
            ]
        );
    }

    /**
     * Get metrics for a date range.
     */
    public static function range(string $from, string $to)
    {
        return self::whereBetween('date', [$from, $to])
            ->orderBy('date')
            ->get();
    }

    /**
     * Get latest N days of metrics.
     */
    public static function latest(int $days = 30)
    {
        return self::where('date', '>=', now()->subDays($days)->toDateString())
            ->orderBy('date')
            ->get();
    }
}
