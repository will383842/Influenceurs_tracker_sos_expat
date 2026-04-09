<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * Runs the press:discover artisan command as a queued job.
 * This allows triggering discovery from the admin dashboard.
 */
class DiscoverPressPublicationsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600; // 10 minutes max
    public int $tries   = 1;

    public function __construct(
        private string $category = 'all',
        private bool   $scrape = true,
    ) {
        $this->onQueue('scraper');
    }

    public function handle(): void
    {
        Log::info('DiscoverPressPublicationsJob: starting', [
            'category' => $this->category,
            'scrape'   => $this->scrape,
        ]);

        $args = ['--category' => $this->category];
        if ($this->scrape) {
            $args['--scrape'] = true;
        }

        Artisan::call('press:discover', $args);

        $output = Artisan::output();
        Log::info('DiscoverPressPublicationsJob: done', ['output' => $output]);
    }
}
