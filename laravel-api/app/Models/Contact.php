<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'influenceur_id', 'user_id', 'date', 'channel', 'direction',
        'result', 'subject', 'sender', 'message', 'reply', 'notes',
        'email_opened_at', 'email_clicked_at', 'template_used',
    ];

    protected $casts = [
        'date'             => 'date',
        'email_opened_at'  => 'datetime',
        'email_clicked_at' => 'datetime',
    ];

    public function influenceur()
    {
        return $this->belongsTo(Influenceur::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
