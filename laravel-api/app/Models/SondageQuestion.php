<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SondageQuestion extends Model
{
    protected $fillable = [
        'sondage_id',
        'text',
        'type',
        'options',
        'sort_order',
    ];

    protected $casts = [
        'options'    => 'array',
        'sort_order' => 'integer',
    ];

    public function sondage(): BelongsTo
    {
        return $this->belongsTo(Sondage::class);
    }
}
