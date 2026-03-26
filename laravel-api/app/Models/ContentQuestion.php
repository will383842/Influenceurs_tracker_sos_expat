<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentQuestion extends Model
{
    protected $table = 'content_questions';

    protected $fillable = [
        'source_id', 'title', 'url', 'url_hash',
        'country', 'country_slug', 'continent', 'city',
        'replies', 'views', 'is_sticky', 'is_closed',
        'last_post_date', 'last_post_author', 'language',
        'article_status', 'article_notes', 'scraped_at',
        'cluster_id', 'qa_entry_id', 'generated_article_id',
    ];

    protected $casts = [
        'replies'    => 'integer',
        'views'      => 'integer',
        'is_sticky'  => 'boolean',
        'is_closed'  => 'boolean',
        'scraped_at' => 'datetime',
    ];

    public function source()
    {
        return $this->belongsTo(ContentSource::class, 'source_id');
    }

    public function cluster()
    {
        return $this->belongsTo(QuestionCluster::class, 'cluster_id');
    }

    public function qaEntry()
    {
        return $this->belongsTo(QaEntry::class, 'qa_entry_id');
    }

    public function generatedArticle()
    {
        return $this->belongsTo(GeneratedArticle::class, 'generated_article_id');
    }

    // ============================================================
    // Accessors
    // ============================================================

    public function getPopularityScoreAttribute(): int
    {
        return ($this->views ?? 0) + ($this->replies ?? 0) * 10;
    }
}
