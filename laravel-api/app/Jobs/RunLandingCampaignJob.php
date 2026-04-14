<?php

namespace App\Jobs;

use App\Models\LandingCampaign;
use App\Models\LandingPage;
use App\Models\LandingProblem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job quotidien qui pilote automatiquement les campagnes de landing pages
 * pour les 4 audiences : clients, lawyers, helpers, matching.
 *
 * - Respecte le daily_limit par audience_type
 * - Avance automatiquement au pays suivant quand le quota pages_per_country est atteint
 * - Marque la campagne 'completed' quand la queue entière est traitée
 * - Génère en langue 'fr' uniquement (les autres langues se lancent manuellement depuis l'UI)
 */
class RunLandingCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;
    public int $tries   = 1;

    // ──────────────────────────────────────────────────────────────
    // Middleware
    // ──────────────────────────────────────────────────────────────

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('run-landing-campaign'))->expireAfter(7200),
        ];
    }

    // ──────────────────────────────────────────────────────────────
    // Constructor
    // ──────────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->onQueue('default');
    }

    // ──────────────────────────────────────────────────────────────
    // Handle
    // ──────────────────────────────────────────────────────────────

    public function handle(): void
    {
        $language = 'fr';

        foreach (LandingCampaign::VALID_TYPES as $type) {
            try {
                $this->processAudience($type, $language);
            } catch (\Throwable $e) {
                Log::error('RunLandingCampaignJob: exception pour audience ' . $type, [
                    'error' => $e->getMessage(),
                    'trace' => substr($e->getTraceAsString(), 0, 500),
                ]);
                // On continue avec les autres audiences malgré l'erreur
            }
        }
    }

    // ──────────────────────────────────────────────────────────────
    // Logique par audience
    // ──────────────────────────────────────────────────────────────

    private function processAudience(string $type, string $language): void
    {
        $campaign = LandingCampaign::findOrCreateForType($type);

        // ── 1. Skip si la campagne est en pause ──────────────────
        if ($campaign->status === 'paused') {
            Log::info("RunLandingCampaignJob: campagne {$type} en pause — skip");
            return;
        }

        // ── 2. Skip si la campagne est terminée ──────────────────
        if ($campaign->status === 'completed') {
            Log::info("RunLandingCampaignJob: campagne {$type} déjà complétée — skip");
            return;
        }

        // ── 3. Vérifier daily_limit ───────────────────────────────
        $dailyLimit = (int) $campaign->daily_limit;
        if ($dailyLimit > 0) {
            $todayCount = LandingPage::where('audience_type', $type)
                ->where('generation_source', 'ai_generated')
                ->whereDate('created_at', today())
                ->count();

            if ($todayCount >= $dailyLimit) {
                Log::info("RunLandingCampaignJob: limite journalière atteinte pour {$type}", [
                    'today_count' => $todayCount,
                    'daily_limit' => $dailyLimit,
                ]);
                return;
            }
        }

        // ── 4. Résoudre le pays courant ───────────────────────────
        $queue = $campaign->country_queue ?? [];

        if (empty($queue)) {
            Log::info("RunLandingCampaignJob: queue vide pour {$type} — skip");
            return;
        }

        $currentCountry = $campaign->current_country;

        // Pas de current_country → prendre le premier de la queue
        if (! $currentCountry) {
            $currentCountry = $queue[0];
            $campaign->update([
                'current_country' => $currentCountry,
                'started_at'      => $campaign->started_at ?? now(),
            ]);
        }

        // ── 5. Vérifier si le pays courant est déjà terminé ──────
        $countForCurrentCountry = LandingPage::where('audience_type', $type)
            ->where('generation_source', 'ai_generated')
            ->where('country_code', $currentCountry)
            ->count();

        if ($countForCurrentCountry >= $campaign->pages_per_country) {
            // Avancer au pays suivant
            $advanced = $this->advanceToNextCountry($campaign, $currentCountry, $queue, $type);
            if (! $advanced) {
                return; // Campagne terminée ou bloquée
            }
            // Recharger la campagne après mise à jour
            $campaign->refresh();
            $currentCountry = $campaign->current_country;

            if (! $currentCountry) {
                return;
            }
        }

        // ── 6. Dispatcher les jobs de génération ──────────────────
        $selectedTemplates = $campaign->selected_templates ?? LandingCampaign::DEFAULT_TEMPLATES[$type] ?? [];

        if (empty($selectedTemplates)) {
            Log::warning("RunLandingCampaignJob: aucun template pour {$type} — skip");
            return;
        }

        $dispatched = 0;

        if ($type === 'clients') {
            $dispatched = $this->dispatchClients($campaign, $currentCountry, $selectedTemplates, $language);
        } else {
            $dispatched = $this->dispatchSimple($type, $campaign, $currentCountry, $selectedTemplates, $language);
        }

        // ── 7. Marquer la campagne en cours ───────────────────────
        if ($dispatched > 0) {
            $campaign->update([
                'status'     => 'running',
                'started_at' => $campaign->started_at ?? now(),
            ]);
        }

        Log::info("RunLandingCampaignJob: {$dispatched} jobs dispatchés pour {$type}/{$currentCountry}", [
            'audience_type' => $type,
            'country'       => $currentCountry,
            'language'      => $language,
            'templates'     => $selectedTemplates,
            'dispatched'    => $dispatched,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Avancement de la queue pays
    // ──────────────────────────────────────────────────────────────

    /**
     * Avance au pays suivant dans la queue.
     * Retourne true si un pays suivant a été trouvé, false si la campagne est terminée.
     */
    private function advanceToNextCountry(LandingCampaign $campaign, string $currentCountry, array $queue, string $type): bool
    {
        $currentIndex = array_search($currentCountry, $queue, true);

        if ($currentIndex === false) {
            Log::warning("RunLandingCampaignJob: pays courant {$currentCountry} absent de la queue {$type}");
            return false;
        }

        $nextIndex = $currentIndex + 1;

        if ($nextIndex < count($queue)) {
            $nextCountry = $queue[$nextIndex];
            $campaign->update(['current_country' => $nextCountry]);
            Log::info("RunLandingCampaignJob: pays avancé {$type}", [
                'from' => $currentCountry,
                'to'   => $nextCountry,
            ]);
            return true;
        }

        // Queue entièrement traitée
        $campaign->update([
            'status'          => 'completed',
            'completed_at'    => now(),
            'current_country' => null,
        ]);
        Log::info("RunLandingCampaignJob: campagne {$type} TERMINÉE — tous les pays traités");
        return false;
    }

    // ──────────────────────────────────────────────────────────────
    // Dispatch audience clients (templates × problems)
    // ──────────────────────────────────────────────────────────────

    private function dispatchClients(
        LandingCampaign $campaign,
        string $countryCode,
        array $templates,
        string $language,
    ): int {
        $filters    = $campaign->problem_filters ?? [];
        $perCountry = $campaign->pages_per_country;

        $query = LandingProblem::active()->ordered();

        if (! empty($filters['categories'])) {
            $query->whereIn('category', $filters['categories']);
        }
        if (! empty($filters['min_urgency'])) {
            $query->minUrgency((int) $filters['min_urgency']);
        }
        if (! empty($filters['business_values'])) {
            $query->byBusinessValue($filters['business_values']);
        }

        $problems   = $query->limit(max(1, (int) ceil($perCountry / count($templates))))->get();
        $dispatched = 0;
        $delay      = 0;

        foreach ($templates as $templateId) {
            foreach ($problems as $problem) {
                $exists = LandingPage::where([
                    'audience_type' => 'clients',
                    'template_id'   => $templateId,
                    'problem_id'    => $problem->slug,
                    'country_code'  => $countryCode,
                    'language'      => $language,
                ])->exists();

                if ($exists) {
                    continue;
                }

                GenerateLandingPageJob::dispatch([
                    'audience_type' => 'clients',
                    'template_id'   => $templateId,
                    'country_code'  => $countryCode,
                    'language'      => $language,
                    'problem_slug'  => $problem->slug,
                ])->delay(now()->addSeconds($delay));

                $delay      += 5; // Anti-throttle Claude API
                $dispatched++;

                if ($dispatched >= $perCountry) {
                    break 2;
                }
            }
        }

        return $dispatched;
    }

    // ──────────────────────────────────────────────────────────────
    // Dispatch audiences lawyers / helpers / matching (1 LP / template)
    // ──────────────────────────────────────────────────────────────

    private function dispatchSimple(
        string $type,
        LandingCampaign $campaign,
        string $countryCode,
        array $templates,
        string $language,
    ): int {
        $dispatched = 0;
        $delay      = 0;

        foreach ($templates as $templateId) {
            $exists = LandingPage::where([
                'audience_type' => $type,
                'template_id'   => $templateId,
                'country_code'  => $countryCode,
                'language'      => $language,
            ])->exists();

            if ($exists) {
                continue;
            }

            GenerateLandingPageJob::dispatch([
                'audience_type' => $type,
                'template_id'   => $templateId,
                'country_code'  => $countryCode,
                'language'      => $language,
            ])->delay(now()->addSeconds($delay));

            $delay      += 5;
            $dispatched++;
        }

        return $dispatched;
    }

    // ──────────────────────────────────────────────────────────────
    // Failure handler
    // ──────────────────────────────────────────────────────────────

    public function failed(\Throwable $e): void
    {
        Log::error('RunLandingCampaignJob: job échoué', [
            'error' => $e->getMessage(),
            'trace' => substr($e->getTraceAsString(), 0, 500),
        ]);
    }
}
