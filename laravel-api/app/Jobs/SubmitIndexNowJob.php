<?php

namespace App\Jobs;

use App\Services\Seo\IndexNowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SubmitIndexNowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 30;
    public int $tries = 3;

    public function __construct(
        public string $url,
    ) {
        $this->onQueue('default');
    }

    public function handle(IndexNowService $service): void
    {
        Log::info('SubmitIndexNowJob started', ['url' => $this->url]);

        $result = $service->submit($this->url);

        Log::info('IndexNow submission result', [
            'url' => $this->url,
            'success' => $result['success'] ?? false,
            'status' => $result['status'] ?? null,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('SubmitIndexNowJob failed', [
            'url' => $this->url,
            'error' => $e->getMessage(),
        ]);
    }
}
