<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LandingCtaLink extends Model
{
    protected $fillable = [
        'landing_page_id', 'url', 'text', 'position', 'style', 'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function landingPage(): BelongsTo
    {
        return $this->belongsTo(LandingPage::class);
    }
}
