<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoAnalysis extends Model
{
    protected $table = 'seo_analyses';

    protected $fillable = [
        'analyzable_type', 'analyzable_id',
        'overall_score', 'title_score', 'meta_description_score',
        'headings_score', 'content_score', 'images_score',
        'internal_links_score', 'external_links_score',
        'structured_data_score', 'hreflang_score', 'technical_score',
        'issues', 'analyzed_at',
    ];

    protected $casts = [
        'overall_score'          => 'integer',
        'title_score'            => 'integer',
        'meta_description_score' => 'integer',
        'headings_score'         => 'integer',
        'content_score'          => 'integer',
        'images_score'           => 'integer',
        'internal_links_score'   => 'integer',
        'external_links_score'   => 'integer',
        'structured_data_score'  => 'integer',
        'hreflang_score'         => 'integer',
        'technical_score'        => 'integer',
        'issues'                 => 'array',
        'analyzed_at'            => 'datetime',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function analyzable(): MorphTo
    {
        return $this->morphTo();
    }
}
