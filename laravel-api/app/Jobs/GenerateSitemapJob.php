<?php

namespace App\Jobs;

use App\Services\Seo\SitemapService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateSitemapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 60;
    public int $tries = 2;

    public function __construct()
    {
        $this->onQueue('default');
    }

    public function handle(SitemapService $service): void
    {
        Log::info('GenerateSitemapJob started');

        $service->saveToDisk();

        Log::info('Sitemap regenerated');
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateSitemapJob failed', [
            'error' => $e->getMessage(),
        ]);
    }
}
