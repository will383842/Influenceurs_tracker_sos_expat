<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScraperRotationState extends Model
{
    protected $table = 'scraper_rotation_state';

    protected $fillable = [
        'scraper_name', 'last_country', 'last_ran_at',
        'country_queue', 'recent_countries',
    ];

    protected $casts = [
        'last_ran_at'      => 'datetime',
        'country_queue'    => 'array',
        'recent_countries' => 'array',
    ];
}
