<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OutreachSequence extends Model
{
    protected $fillable = [
        'influenceur_id', 'current_step', 'status',
        'stop_reason', 'next_send_at', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'next_send_at' => 'datetime',
        'started_at'   => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function influenceur(): BelongsTo
    {
        return $this->belongsTo(Influenceur::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeReadyToAdvance($query)
    {
        return $query->where('status', 'active')
            ->whereNotNull('next_send_at')
            ->where('next_send_at', '<=', now());
    }
}
