<?php

namespace App\Jobs;

use App\Models\RssFeedItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RunNewsGenerationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 1;

    public function handle(): array
    {
        // ── Quota journalier ──
        $quotaInfo    = $this->getQuotaInfo();
        $quotaLimit   = $quotaInfo['quota'];
        $generatedToday = $quotaInfo['generated_today'];
        $remaining    = max(0, $quotaLimit - $generatedToday);

        if ($remaining <= 0) {
            Log::info('RunNewsGenerationJob: quota journalier atteint, aucun dispatch');
            return ['dispatched' => 0, 'remaining_quota' => 0];
        }

        // ── Récupérer les items pending pertinents ──
        $items = RssFeedItem::where('status', 'pending')
            ->whereNotNull('relevance_score')
            ->orderByDesc('relevance_score')
            ->orderByDesc('published_at')
            ->limit($remaining)
            ->get();

        $dispatched = 0;
        $seenTitles = [];

        foreach ($items as $item) {
            // Dedup: skip items with same title (same article from different RSS feeds)
            $normalizedTitle = mb_strtolower(trim($item->title));
            if (isset($seenTitles[$normalizedTitle])) {
                $item->update(['status' => 'skipped', 'error_message' => 'Duplicate title from another feed']);
                continue;
            }
            $seenTitles[$normalizedTitle] = true;

            GenerateNewsArticleJob::dispatch($item->id);
            $dispatched++;
        }

        Log::info("RunNewsGenerationJob: {$dispatched} jobs dispatchés, quota restant: " . ($remaining - $dispatched));

        return [
            'dispatched'      => $dispatched,
            'remaining_quota' => $remaining - $dispatched,
        ];
    }

    public function failed(\Throwable $e): void
    {
        Log::error('RunNewsGenerationJob failed permanently', [
            'error' => $e->getMessage(),
        ]);

        $botToken = config('services.telegram_alerts.bot_token');
        $chatId = config('services.telegram_alerts.chat_id');
        if ($botToken && $chatId) {
            try {
                Http::post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'parse_mode' => 'Markdown',
                    'text' => "🚨 *Job Failed*: `RunNewsGenerationJob`\n" .
                              "Error: " . mb_substr($e->getMessage(), 0, 500) . "\n" .
                              "Time: " . now()->toDateTimeString(),
                ]);
            } catch (\Throwable $tgError) {
                Log::warning('Failed to send Telegram alert', [
                    'error' => $tgError->getMessage(),
                ]);
            }
        }
    }

    // ─────────────────────────────────────────
    // QUOTA
    // ─────────────────────────────────────────

    /**
     * Self-healing daily counter: derive `generated_today` from a LIVE COUNT
     * of actually-published news items today, instead of trusting a stored
     * counter that gets incremented optimistically.
     *
     * Why this matters: the previous implementation incremented a stored
     * counter inside `reserveQuotaSlot()` BEFORE the generation actually
     * succeeded. If the LLM call failed (e.g. Anthropic credit exhausted on
     * 2026-04-11), the slot was burned without producing anything, and the
     * pseudo-counter rapidly hit 15/15 with zero real news published, locking
     * the pipeline for the rest of the day. With a live count, failed
     * tentatives never inflate the counter.
     *
     * `quota` (the daily LIMIT) is still read from settings so it can be
     * tuned without a deploy.
     */
    private function getQuotaInfo(): array
    {
        try {
            $raw   = DB::table('settings')->where('key', 'news_daily_quota')->value('value');
            $quota = $raw ? json_decode($raw, true) : [];
            $quotaLimit = (int) ($quota['quota'] ?? 15);

            // LIVE COUNT — only published news from today count against the quota.
            $generatedToday = (int) DB::table('rss_feed_items')
                ->where('status', 'published')
                ->whereDate('generated_at', now()->toDateString())
                ->count();

            return ['quota' => $quotaLimit, 'generated_today' => $generatedToday];

        } catch (\Throwable $e) {
            Log::warning('RunNewsGenerationJob: erreur lecture quota', ['error' => $e->getMessage()]);
            return ['quota' => 15, 'generated_today' => 0];
        }
    }
}
