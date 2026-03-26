<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContentTemplateBlock extends Model
{
    protected $fillable = [
        'name', 'description', 'content_type', 'language',
        'html_template', 'variables', 'is_active',
    ];

    protected $casts = [
        'variables' => 'array',
        'is_active' => 'boolean',
    ];
}
