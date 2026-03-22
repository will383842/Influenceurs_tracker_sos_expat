<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutoCampaignTask extends Model
{
    protected $fillable = [
        'campaign_id', 'contact_type', 'country', 'language',
        'status', 'attempt', 'ai_session_id',
        'contacts_found', 'contacts_imported', 'error_message',
        'started_at', 'completed_at', 'next_retry_at', 'priority',
    ];

    protected $casts = [
        'started_at'    => 'datetime',
        'completed_at'  => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AutoCampaign::class, 'campaign_id');
    }

    public function aiSession(): BelongsTo
    {
        return $this->belongsTo(AiResearchSession::class, 'ai_session_id');
    }

    // ============================================================
    // Scopes
    // ============================================================

    /**
     * Get the next task ready to be processed.
     * Respects retry timing and priority ordering.
     */
    public function scopeReadyToProcess($query)
    {
        // IMPORTANT: wrapped in a single where() to prevent OR from escaping
        // the parent campaign_id constraint in hasMany relationship queries.
        return $query->where(function ($q) {
            $q->where('status', 'pending')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'failed')
                     ->whereNotNull('next_retry_at')
                     ->where('next_retry_at', '<=', now());
              });
        });
    }

    // ============================================================
    // Helpers
    // ============================================================

    public function markRunning(): void
    {
        $this->update([
            'status'     => 'running',
            'attempt'    => $this->attempt + 1,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(int $contactsFound, int $contactsImported, ?int $aiSessionId = null): void
    {
        $this->update([
            'status'            => 'completed',
            'contacts_found'    => $contactsFound,
            'contacts_imported' => $contactsImported,
            'ai_session_id'     => $aiSessionId,
            'completed_at'      => now(),
            'error_message'     => null,
            'next_retry_at'     => null,
        ]);
    }

    public function markFailed(string $error, int $maxRetries): void
    {
        $canRetry = $this->attempt < $maxRetries;

        // Exponential backoff: 10min, 20min, 40min
        $backoffSeconds = $canRetry
            ? (int) (600 * pow(2, $this->attempt - 1))
            : null;

        $this->update([
            'status'        => 'failed',
            'error_message' => mb_substr($error, 0, 1000),
            'completed_at'  => now(),
            'next_retry_at' => $canRetry ? now()->addSeconds($backoffSeconds) : null,
        ]);
    }

    public function markSkipped(string $reason): void
    {
        $this->update([
            'status'        => 'skipped',
            'error_message' => $reason,
            'completed_at'  => now(),
        ]);
    }

    /**
     * Whether this task can still be retried.
     */
    public function canRetry(int $maxRetries): bool
    {
        return $this->status === 'failed' && $this->attempt < $maxRetries;
    }
}
