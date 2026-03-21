<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value'];

    public static function get(string $key, string $default = ''): string
    {
        return Cache::remember("setting_{$key}", 300, function () use ($key, $default) {
            return self::where('key', $key)->value('value') ?? $default;
        });
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::get($key, $default ? 'true' : 'false');
        return in_array(strtolower($val), ['true', '1', 'yes', 'on']);
    }

    public static function set(string $key, string $value): void
    {
        self::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget("setting_{$key}");
    }
}
