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
     * Atomically check quota and increment if allowed.
     * Uses a Cache lock to prevent race conditions between parallel jobs.
     * Returns true if a slot was reserved, false if quota exhausted.
     */
    private function reserveQuotaSlot(): bool
    {
        $lock = Cache::lock('news_quota_lock', 10); // lock 10 secondes max

        try {
            $lock->block(5); // attendre max 5s pour obtenir le lock

            $raw   = DB::table('settings')->where('key', 'news_daily_quota')->value('value');
            $quota = $raw ? json_decode($raw, true) : [];

            $quotaLimit     = (int) ($quota['quota'] ?? 15);
            $generatedToday = (int) ($quota['generated_today'] ?? 0);
            $today          = now()->toDateString();

            // Reset si nouveau jour
            if (($quota['last_reset_date'] ?? '') !== $today) {
                $generatedToday = 0;
            }

            if ($generatedToday >= $quotaLimit) {
                return false;
            }

            // Réserver le slot
            DB::table('settings')->updateOrInsert(
                ['key' => 'news_daily_quota'],
                [
                    'value'      => json_encode([
                        'quota'           => $quotaLimit,
                        'generated_today' => $generatedToday + 1,
                        'last_reset_date' => $today,
                    ]),
                    'updated_at' => now(),
                ]
            );

            return true;

        } catch (\Illuminate\Contracts\Cache\LockTimeoutException $e) {
            Log::warning('GenerateNewsArticleJob: timeout quota lock, on laisse passer par sécurité');
            return true;
        } catch (\Throwable $e) {
            Log::warning('GenerateNewsArticleJob: erreur quota', ['error' => $e->getMessage()]);
            return true; // En cas d'erreur DB, on laisse passer
        } finally {
            $lock->release();
        }
    }
}
