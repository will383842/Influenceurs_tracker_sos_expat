<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarmupState extends Model
{
    protected $fillable = [
        'from_email', 'domain', 'day_count',
        'emails_sent_today', 'current_daily_limit',
        'started_at', 'last_sent_at', 'last_reset_date',
    ];

    protected $casts = [
        'started_at'   => 'date',
        'last_sent_at' => 'datetime',
        'last_reset_date' => 'date',
    ];

    /**
     * Get or create warmup state for a sending address.
     */
    public static function getFor(string $fromEmail): self
    {
        $domain = substr($fromEmail, strpos($fromEmail, '@') + 1);

        return static::firstOrCreate(
            ['from_email' => $fromEmail],
            [
                'domain'              => $domain,
                'day_count'           => 0,
                'emails_sent_today'   => 0,
                'current_daily_limit' => 5,
                'started_at'          => today(),
                'last_reset_date'     => today(),
            ]
        );
    }

    /**
     * Check if we can still send today.
     */
    public function canSend(): bool
    {
        $this->resetDailyIfNeeded();
        return $this->emails_sent_today < $this->current_daily_limit;
    }

    /**
     * Record an email sent.
     */
    public function recordSent(): void
    {
        $this->resetDailyIfNeeded();
        $this->increment('emails_sent_today');
        $this->update(['last_sent_at' => now()]);
    }

    /**
     * Reset daily counter and advance warm-up if needed.
     */
    public function resetDailyIfNeeded(): void
    {
        if ($this->last_reset_date && $this->last_reset_date->toDateString() === today()->toDateString()) {
            return; // Already reset today
        }

        // Atomic daily reset to prevent race conditions
        $affected = static::where('id', $this->id)
            ->where(function ($q) {
                $q->whereNull('last_reset_date')
                  ->orWhere('last_reset_date', '<', today());
            })
            ->update([
                'day_count'           => \Illuminate\Support\Facades\DB::raw('day_count + 1'),
                'emails_sent_today'   => 0,
                'last_reset_date'     => today(),
                'current_daily_limit' => $this->calculateLimit(),
            ]);

        if ($affected > 0) {
            $this->refresh(); // Reload from DB
        }
    }

    /**
     * Progressive warm-up limits.
     * Day 1-3:  5/day
     * Day 4-7:  15/day
     * Day 8-14: 30/day
     * Day 15+:  50/day
     */
    private function calculateLimit(): int
    {
        $day = $this->day_count;
        if ($day <= 3) return 5;
        if ($day <= 7) return 15;
        if ($day <= 14) return 30;
        return 50;
    }
}
