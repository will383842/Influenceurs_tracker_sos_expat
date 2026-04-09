<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutoCampaign extends Model
{
    protected $fillable = [
        'name', 'status',
        'contact_types', 'countries', 'languages',
        'delay_between_tasks_seconds', 'delay_between_retries_seconds', 'max_retries',
        'tasks_total', 'tasks_completed', 'tasks_failed', 'tasks_skipped',
        'contacts_found_total', 'contacts_imported_total', 'total_cost_cents',
        'consecutive_failures', 'max_consecutive_failures',
        'started_at', 'completed_at', 'last_task_at',
        'created_by', 'queue_position',
        'auto_restart', 'restart_delay_hours', 'cycles_completed',
    ];

    protected $casts = [
        'contact_types'  => 'array',
        'countries'      => 'array',
        'languages'      => 'array',
        'started_at'     => 'datetime',
        'completed_at'   => 'datetime',
        'last_task_at'   => 'datetime',
        'auto_restart'   => 'boolean',
    ];

    protected $appends = ['progress'];

    // ============================================================
    // Relationships
    // ============================================================

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(AutoCampaignTask::class, 'campaign_id');
    }

    // ============================================================
    // Scopes
    // ============================================================

    public function scopeRunning($query)
    {
        return $query->where('status', 'running');
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', ['pending', 'running', 'queued']);
    }

    public function scopeQueued($query)
    {
        return $query->where('status', 'queued');
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function isReadyForNextTask(): bool
    {
        if ($this->status !== 'running') {
            return false;
        }

        // Circuit breaker: too many consecutive failures → auto-pause
        if ($this->consecutive_failures >= $this->max_consecutive_failures) {
            return false;
        }

        // Rate limit: respect delay between tasks
        if ($this->last_task_at) {
            $elapsed = (int) abs(now()->diffInSeconds($this->last_task_at));
            if ($elapsed < $this->delay_between_tasks_seconds) {
                return false;
            }
        }

        return true;
    }

    public function recordTaskSuccess(int $contactsFound, int $contactsImported, int $costCents): void
    {
        $this->increment('tasks_completed');
        $this->increment('contacts_found_total', $contactsFound);
        $this->increment('contacts_imported_total', $contactsImported);
        $this->increment('total_cost_cents', $costCents);
        $this->update([
            'consecutive_failures' => 0,
            'last_task_at'         => now(),
        ]);
    }

    public function recordTaskFailure(): void
    {
        $this->increment('tasks_failed');
        $this->increment('consecutive_failures');
        $this->refresh(); // Sync in-memory values after increment()
        $this->update(['last_task_at' => now()]);

        // Auto-pause on circuit breaker
        if ($this->consecutive_failures >= $this->max_consecutive_failures) {
            $this->update(['status' => 'paused']);
        }
    }

    public function recordTaskSkipped(): void
    {
        $this->increment('tasks_skipped');
        $this->update(['last_task_at' => now()]);
    }

    public function checkCompletion(): void
    {
        $remaining = $this->tasks()
            ->whereIn('status', ['pending', 'running'])
            ->count();

        if ($remaining === 0) {
            $this->increment('cycles_completed');

            if ($this->auto_restart) {
                // Perpetual mode: regenerate tasks and restart after delay
                $this->restartCycle();
            } else {
                $this->update([
                    'status'       => 'completed',
                    'completed_at' => now(),
                ]);

                // Auto-start next queued campaign
                static::startNextQueued();
            }
        }
    }

    /**
     * Restart the campaign for a new cycle (perpetual mode).
     * Regenerates all tasks, resets counters, respects restart_delay_hours.
     */
    public function restartCycle(): void
    {
        $delayHours = $this->restart_delay_hours ?? 24;

        \Illuminate\Support\Facades\Log::info('AutoCampaign: perpetual restart scheduled', [
            'campaign_id'      => $this->id,
            'cycles_completed' => $this->cycles_completed,
            'restart_in_hours' => $delayHours,
        ]);

        // Delete old tasks (keep history via cycles_completed counter)
        $this->tasks()->delete();

        // Regenerate tasks for all type × country combinations
        $this->regenerateTasks();

        // Schedule restart: pause now, a scheduled job will resume after delay
        $this->update([
            'status'                => 'paused',
            'completed_at'          => now(),
            'consecutive_failures'  => 0,
            'tasks_completed'       => 0,
            'tasks_failed'          => 0,
            'tasks_skipped'         => 0,
            // Store when to auto-resume
            'started_at'            => now()->addHours($delayHours),
        ]);
    }

    /**
     * Regenerate all tasks for this campaign (type × country × language combinations).
     */
    public function regenerateTasks(): void
    {
        $types     = $this->contact_types ?? [];
        $countries = $this->countries ?? [];
        $languages = $this->languages ?? ['fr'];

        $taskCount = 0;
        foreach ($types as $type) {
            foreach ($countries as $country) {
                $lang = $languages[0] ?? 'fr'; // Primary language
                AutoCampaignTask::create([
                    'campaign_id'  => $this->id,
                    'contact_type' => $type,
                    'country'      => $country,
                    'language'     => $lang,
                    'status'       => 'pending',
                ]);
                $taskCount++;
            }
        }

        $this->update(['tasks_total' => $taskCount]);
    }

    /**
     * Check if a paused perpetual campaign is ready to auto-resume.
     */
    public function isReadyToAutoResume(): bool
    {
        return $this->auto_restart
            && $this->status === 'paused'
            && $this->started_at
            && now()->gte($this->started_at);
    }

    /**
     * Start the next queued campaign (FIFO order).
     */
    public static function startNextQueued(): void
    {
        // Only start if no campaign is currently running
        if (static::running()->exists()) {
            return;
        }

        $next = static::where('status', 'queued')
            ->orderBy('queue_position')
            ->orderBy('id')
            ->first();

        if ($next) {
            $next->update([
                'status'     => 'running',
                'started_at' => now(),
            ]);

            \Illuminate\Support\Facades\Log::info('AutoCampaign: auto-started queued campaign', [
                'campaign_id' => $next->id,
                'name'        => $next->name,
            ]);
        }
    }

    /**
     * Percentage completion (0-100).
     */
    public function getProgressAttribute(): int
    {
        if ($this->tasks_total === 0) return 0;
        $done = $this->tasks_completed + $this->tasks_failed + $this->tasks_skipped;
        return (int) round(($done / $this->tasks_total) * 100);
    }
}
