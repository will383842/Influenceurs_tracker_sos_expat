<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CountryGeo extends Model
{
    protected $table = 'countries_geo';
    protected $primaryKey = 'country_code';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'country_code', 'country_name_fr', 'country_name_en',
        'latitude', 'longitude', 'capital_fr', 'capital_en',
        'official_language', 'currency_code', 'currency_name',
        'region', 'expat_approx', 'timezone',
    ];

    protected $casts = [
        'latitude'     => 'decimal:6',
        'longitude'    => 'decimal:6',
        'expat_approx' => 'integer',
    ];

    /**
     * Get geo position string for meta geo.position (lat;lon).
     */
    public function getGeoPositionAttribute(): string
    {
        return $this->latitude . ';' . $this->longitude;
    }

    /**
     * Get ICBM string (lat, lon).
     */
    public function getIcbmAttribute(): string
    {
        return $this->latitude . ', ' . $this->longitude;
    }

    /**
     * Find by country code (case-insensitive).
     */
    public static function findByCode(string $code): ?self
    {
        return static::find(strtoupper(trim($code)));
    }

    /**
     * Get country name in given language (fr or en).
     */
    public function getName(string $lang = 'fr'): string
    {
        return $lang === 'en' ? $this->country_name_en : $this->country_name_fr;
    }
}
