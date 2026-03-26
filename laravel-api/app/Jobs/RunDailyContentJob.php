<?php

namespace App\Jobs;

use App\Services\Content\DailyContentSchedulerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class RunDailyContentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 14400; // 4 hours
    public int $tries = 1;
    public int $maxExceptions = 1;

    public function __construct()
    {
        $this->onQueue('content');
    }

    public function handle(DailyContentSchedulerService $service): void
    {
        Log::info('RunDailyContentJob: started');

        $log = $service->runDaily();

        Log::info('RunDailyContentJob: completed', [
            'total_generated' => $log->total_generated,
            'published'       => $log->published,
            'cost_cents'      => $log->total_cost_cents,
            'errors'          => $log->errors ? count($log->errors) : 0,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RunDailyContentJob: failed', [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }
}
