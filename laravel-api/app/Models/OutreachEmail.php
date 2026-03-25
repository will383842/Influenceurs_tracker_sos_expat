<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class OutreachEmail extends Model
{
    protected $fillable = [
        'influenceur_id', 'template_id', 'step',
        'subject', 'body_html', 'body_text',
        'from_email', 'from_name', 'status',
        'ai_generated', 'ai_model', 'ai_prompt_tokens', 'ai_completion_tokens',
        'send_after', 'sent_at', 'delivered_at', 'opened_at', 'clicked_at',
        'replied_at', 'bounced_at', 'bounce_type', 'bounce_reason',
        'external_id', 'tracking_id', 'unsubscribe_token', 'error_message',
    ];

    protected $casts = [
        'ai_generated' => 'boolean',
        'send_after'   => 'datetime',
        'sent_at'      => 'datetime',
        'delivered_at'  => 'datetime',
        'opened_at'    => 'datetime',
        'clicked_at'   => 'datetime',
        'replied_at'   => 'datetime',
        'bounced_at'   => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $email) {
            if (!$email->tracking_id) {
                $email->tracking_id = Str::uuid();
            }
            if (!$email->unsubscribe_token) {
                $email->unsubscribe_token = Str::uuid();
            }
        });
    }

    public function influenceur(): BelongsTo
    {
        return $this->belongsTo(Influenceur::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    // Scopes
    public function scopePendingReview($query)
    {
        return $query->where('status', 'pending_review');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeReadyToSend($query)
    {
        return $query->where('status', 'approved')
            ->where(fn($q) => $q->whereNull('send_after')->orWhere('send_after', '<=', now()));
    }
}
