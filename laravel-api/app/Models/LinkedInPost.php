<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\AsArray;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * LinkedIn post record.
 *
 * status: generating | draft | scheduled | published | failed
 * phase:  1 = Francophone clients (FR dominant)
 *         2 = Global expansion (FR + EN)
 */
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
        'phase'        => 'integer',
        'reach'        => 'integer',
        'likes'        => 'integer',
        'comments'     => 'integer',
        'shares'       => 'integer',
        'clicks'       => 'integer',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function article(): BelongsTo
    {
        return $this->belongsTo(GeneratedArticle::class, 'source_id');
    }

    public function faq(): BelongsTo
    {
        return $this->belongsTo(QaEntry::class, 'source_id');
    }
}
