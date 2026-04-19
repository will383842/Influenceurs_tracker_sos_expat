<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScraperRun extends Model
{
    public const STATUS_RUNNING        = 'running';
    public const STATUS_OK             = 'ok';
    public const STATUS_SKIPPED_NO_IA  = 'skipped_no_ia';
    public const STATUS_RATE_LIMITED   = 'rate_limited';
    public const STATUS_CIRCUIT_BROKEN = 'circuit_broken';
    public const STATUS_ERROR          = 'error';

    protected $fillable = [
        'scraper_name', 'status', 'country',
        'contacts_found', 'contacts_new',
        'started_at', 'ended_at',
        'error_message', 'requires_perplexity', 'meta',
    ];

    protected $casts = [
        'started_at'          => 'datetime',
        'ended_at'            => 'datetime',
        'contacts_found'      => 'integer',
        'contacts_new'        => 'integer',
        'requires_perplexity' => 'boolean',
        'meta'                => 'array',
    ];

    public function markOk(int $found = 0, int $new = 0, ?array $meta = null): void
    {
        $this->update([
            'status'         => self::STATUS_OK,
            'ended_at'       => now(),
            'contacts_found' => $found,
            'contacts_new'   => $new,
            'meta'           => $meta ?? $this->meta,
        ]);
    }

    public function markError(string $message, string $status = self::STATUS_ERROR): void
    {
        $this->update([
            'status'        => $status,
            'ended_at'      => now(),
            'error_message' => substr($message, 0, 1000),
        ]);
    }
}
