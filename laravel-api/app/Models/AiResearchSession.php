<?php

namespace App\Models;

use App\Enums\ContactType;
use Illuminate\Database\Eloquent\Model;

class AiResearchSession extends Model
{
    protected $fillable = [
        'user_id', 'contact_type', 'country', 'language',
        'claude_response', 'perplexity_response', 'tavily_response',
        'parsed_contacts', 'excluded_domains',
        'contacts_found', 'contacts_imported', 'contacts_duplicates',
        'tokens_used', 'cost_cents',
        'status', 'started_at', 'completed_at', 'error_message',
    ];

    protected $casts = [
        'contact_type'      => ContactType::class,
        'parsed_contacts'   => 'array',
        'excluded_domains'  => 'array',
        'started_at'        => 'datetime',
        'completed_at'      => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function markRunning(): void
    {
        $this->update([
            'status'     => 'running',
            'started_at' => now(),
        ]);
    }

    public function markCompleted(array $parsedContacts, int $tokensUsed = 0): void
    {
        $this->update([
            'status'           => 'completed',
            'completed_at'     => now(),
            'parsed_contacts'  => $parsedContacts,
            'contacts_found'   => count($parsedContacts),
            'tokens_used'      => $tokensUsed,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status'        => 'failed',
            'completed_at'  => now(),
            'error_message' => $error,
        ]);
    }
}
