<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'influenceur_id', 'action', 'details',
        'is_manual', 'manual_note', 'contact_type',
    ];

    protected $casts = [
        'details'    => 'array',
        'is_manual'  => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $log) {
            $log->created_at = $log->created_at ?? now();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function influenceur()
    {
        return $this->belongsTo(Influenceur::class);
    }
}
