<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionClusterItem extends Model
{
    protected $fillable = [
        'cluster_id', 'question_id',
        'is_primary', 'similarity_score',
    ];

    protected $casts = [
        'is_primary'       => 'boolean',
        'similarity_score' => 'decimal:2',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function cluster(): BelongsTo
    {
        return $this->belongsTo(QuestionCluster::class, 'cluster_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(ContentQuestion::class, 'question_id');
    }
}
