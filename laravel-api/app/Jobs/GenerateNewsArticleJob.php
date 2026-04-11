<?php

namespace App\Jobs;

use App\Models\RssFeedItem;
use App\Services\News\NewsGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class GenerateNewsArticleJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;
    public int $tries   = 2;
    public array $backoff = [60, 300];

    public function __construct(private readonly int $itemId) {}

    public function handle(NewsGenerationService $service): void
    {
        $item = RssFeedItem::find($this->itemId);

        if (! $item) {
            Log::warning("GenerateNewsArticleJob: item #{$this->itemId} introuvable");
            return;
        }

        // Accepte pending ET generating (retry après crash)
        if (! in_array($item->status, ['pending', 'generating'], true)) {
            Log::info("GenerateNewsArticleJob: item #{$this->itemId} status={$item->status}, skip");
            return;
        }

        // ── Vérification + réservation quota (atomique via Cache lock) ──
        $allowed = $this->reserveQuotaSlot();
        if (! $allowed) {
            Log::info("GenerateNewsArticleJob: quota journalier atteint, item #{$this->itemId} reporté");
            // Remettre en pending si on était en generating
            if ($item->status === 'generating') {
                $item->update(['status' => 'pending']);
            }
            return;
        }

        // ── Génération ──
        $item->update(['status' => 'generating']);

        try {
            $success = $service->generate($item);

            if ($success) {
                Log::info("GenerateNewsArticleJob: item #{$this->itemId} publié avec succès");
            } else {
                // Slot consommé mais génération échouée → on ne restitue pas (évite boucle infinie)
                Log::warning("GenerateNewsArticleJob: échec génération item #{$this->itemId}");
            }

        } catch (\Throwable $e) {
            Log::error("GenerateNewsArticleJob: exception item #{$this->itemId}", ['error' => $e->getMessage()]);
            $item->update([
                'status'        => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    // ─────────────────────────────────────────
    // QUOTA — atomique via Cache lock
    // ─────────────────────────────────────────

    /**
     * Check daily quota by LIVE COUNT of published news today.
     * No more optimistic increment — failed generations don't burn slots.
     * See RunNewsGenerationJob::getQuotaInfo() for the rationale.
     */
    private function reserveQuotaSlot(): bool
    {
        try {
            $raw   = DB::table('settings')->where('key', 'news_daily_quota')->value('value');
            $quota = $raw ? json_decode($raw, true) : [];
            $quotaLimit = (int) ($quota['quota'] ?? 15);

            $generatedToday = (int) DB::table('rss_feed_items')
                ->where('status', 'published')
                ->whereDate('generated_at', now()->toDateString())
                ->count();

            return $generatedToday < $quotaLimit;

        } catch (\Throwable $e) {
            Log::warning('GenerateNewsArticleJob: erreur quota', ['error' => $e->getMessage()]);
            return true; // En cas d'erreur DB, on laisse passer
        }
    }
}
