<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArray;

class LinkedInPost extends Model
{
    protected $fillable = [
        'source_type', 'source_id', 'source_title',
        'day_type', 'lang', 'account',
        'hook', 'body', 'hashtags',
        'status', 'scheduled_at', 'published_at',
        'li_post_id_page', 'li_post_id_personal',
        'reach', 'likes', 'comments', 'shares', 'clicks', 'engagement_rate',
        'phase', 'error_message',
    ];

    protected $casts = [
        'hashtags'     => AsArray::class,
        'scheduled_at' => 'datetime',
        'published_at' => 'datetime',
    ];
}
