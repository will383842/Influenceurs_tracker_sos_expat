<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BrandGuideline extends Model
{
    protected $fillable = [
        'name', 'description', 'rules', 'is_active',
    ];

    protected $casts = [
        'rules'     => 'array',
        'is_active' => 'boolean',
    ];
}
