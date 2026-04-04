<?php

use App\Jobs\CheckRemindersJob;
use App\Jobs\FetchRssFeedsJob;
use App\Jobs\ProcessAutoCampaignJob;
use App\Jobs\ProcessEmailQueueJob;
use App\Jobs\ProcessSequencesJob;
use App\Jobs\RunDailyContentJob;
use App\Jobs\RunNewsGenerationJob;
use App\Jobs\RunQualityVerificationJob;
use App\Jobs\RunScraperBatchJob;
use App\Models\RssFeedItem;
use Illuminate\Support\Facades\Schedule;

Schedule::job(new CheckRemindersJob)->hourly();

// Daily database backup at 3:00 AM UTC
Schedule::command('backup:database')->dailyAt('03:00')->withoutOverlapping();

// Web scraper: dispatch batch of contacts to scrape every hour
Schedule::job(new RunScraperBatchJob)->hourly()->withoutOverlapping();

// Auto campaigns: check for next task to process every minute
// The job itself handles rate limiting (default 5min between tasks)
Schedule::job(new ProcessAutoCampaignJob)->everyMinute()->withoutOverlapping();

// Quality verification: run full pipeline every hour
Schedule::job(new RunQualityVerificationJob)->hourly()->withoutOverlapping();

// Outreach: send approved emails every 5 minutes
Schedule::job(new ProcessEmailQueueJob)->everyFiveMinutes()->withoutOverlapping();

// Outreach: advance sequences (generate next step) every 15 minutes
Schedule::job(new ProcessSequencesJob)->everyFifteenMinutes()->withoutOverlapping();

// Run daily content generation at 6:00 AM
Schedule::job(new RunDailyContentJob)->dailyAt('06:00')->withoutOverlapping(14400);

// Q/R Blog auto-generation at 7:00 AM UTC (if active in settings)
Schedule::command('qr:daily-generate')->dailyAt('07:00')->withoutOverlapping(7200);

// Fetch RSS feeds every 4 hours
Schedule::job(new FetchRssFeedsJob)->everyFourHours()->withoutOverlapping(3600);

// Auto-generate news articles at 8:00 AM UTC
Schedule::job(new RunNewsGenerationJob)->dailyAt('08:00')->withoutOverlapping(7200);

// News stale recovery: remettre en pending les items bloqués en 'generating' depuis >30 min
// (cas de crash de worker pendant la génération)
Schedule::call(function () {
    $staleCount = RssFeedItem::where('status', 'generating')
        ->where('updated_at', '<', now()->subMinutes(30))
        ->update(['status' => 'pending', 'error_message' => null]);

    if ($staleCount > 0) {
        \Illuminate\Support\Facades\Log::info("News stale recovery: {$staleCount} items remis en pending");
    }
})->everyFifteenMinutes()->name('news-stale-recovery')->withoutOverlapping();
