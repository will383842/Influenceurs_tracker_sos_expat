<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchBrief extends Model
{
    protected $fillable = [
        'cluster_id',
        'perplexity_response',
        'extracted_facts', 'recent_data', 'identified_gaps',
        'paa_questions', 'suggested_keywords', 'suggested_structure',
        'tokens_used', 'cost_cents',
    ];

    protected $casts = [
        'extracted_facts'      => 'array',
        'recent_data'          => 'array',
        'identified_gaps'      => 'array',
        'paa_questions'        => 'array',
        'suggested_keywords'   => 'array',
        'suggested_structure'  => 'array',
        'tokens_used'          => 'integer',
        'cost_cents'           => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(TopicCluster::class, 'cluster_id');
    }
}
