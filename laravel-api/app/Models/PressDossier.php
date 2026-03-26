<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PressDossier extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'language', 'description', 'cover_image_url',
        'status', 'created_by',
    ];

    // ============================================================
    // Relationships
    // ============================================================

    public function items(): HasMany
    {
        return $this->hasMany(PressDossierItem::class, 'dossier_id')->orderBy('sort_order');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
