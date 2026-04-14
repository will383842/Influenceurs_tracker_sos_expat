<?php

namespace App\Jobs;

use App\Models\LandingCampaign;
use App\Models\LandingPage;
use App\Models\PublicationQueueItem;
use App\Models\PublishingEndpoint;
use App\Services\Content\LandingGenerationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateLandingPageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;   // 10 min max
    public int $tries   = 3;
    public int $maxExceptions = 2;

    public function backoff(): array
    {
        return [60, 300]; // 1 min puis 5 min (identique à GenerateArticleJob)
    }

    /**
     * @param array{
     *   audience_type: string,
     *   template_id: string,
     *   country_code: string,
     *   language: string,
     *   problem_slug?: string|null,
     *   created_by?: int|null,
     * } $params
     */
    public function __construct(
        public readonly array $params,
    ) {
        $this->onQueue('landings');
    }

    public function handle(LandingGenerationService $service): void
    {
        Log::info('GenerateLandingPageJob started', [
            'audience_type' => $this->params['audience_type'],
            'template_id'   => $this->params['template_id'],
            'country_code'  => $this->params['country_code'],
            'problem_slug'  => $this->params['problem_slug'] ?? null,
        ]);

        $landing = $service->generate($this->params);

        // N'incrémenter que si la LP vient d'être créée (pas un hit de déduplication).
        // Avec 3 réplicas, deux workers peuvent recevoir le même job simultanément.
        if ($landing->wasRecentlyCreated) {
            LandingCampaign::where('audience_type', $this->params['audience_type'])
                ->increment('total_generated');

            if ($landing->generation_cost_cents > 0) {
                LandingCampaign::where('audience_type', $this->params['audience_type'])
                    ->increment('total_cost_cents', $landing->generation_cost_cents);
            }

            // Vérifier si le pays courant est terminé → avancer au suivant
            $this->advanceCountryIfComplete(
                $this->params['audience_type'],
                $this->params['country_code'],
            );

            // Auto-publier vers le blog
            $this->autoPublish($landing);
        }

        Log::info('GenerateLandingPageJob completed', [
            'landing_id'    => $landing->id,
            'slug'          => $landing->slug,
            'audience_type' => $this->params['audience_type'],
            'country_code'  => $this->params['country_code'],
            'template_id'   => $this->params['template_id'],
            'seo_score'     => $landing->seo_score,
        ]);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('GenerateLandingPageJob failed', [
            'params'    => $this->params,
            'error'     => $e->getMessage(),
            'trace'     => substr($e->getTraceAsString(), 0, 500),
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    // Helpers privés
    // ──────────────────────────────────────────────────────────────

    /**
     * Vérifie si le quota pages_per_country est atteint pour ce pays.
     * Si oui, avance current_country au suivant (ou marque la campagne terminée).
     */
    private function advanceCountryIfComplete(string $audienceType, string $countryCode): void
    {
        $campaign = LandingCampaign::where('audience_type', $audienceType)->first();

        if (! $campaign || $campaign->current_country !== $countryCode) {
            return;
        }

        $count = LandingPage::where('audience_type', $audienceType)
            ->where('generation_source', 'ai_generated')
            ->where('country_code', $countryCode)
            ->count();

        if ($count < $campaign->pages_per_country) {
            return;
        }

        // Avancer au pays suivant dans la queue
        $queue        = $campaign->country_queue ?? [];
        $currentIndex = array_search($countryCode, $queue, true);

        if ($currentIndex === false) {
            return;
        }

        $nextIndex = $currentIndex + 1;

        if ($nextIndex < count($queue)) {
            $campaign->update(['current_country' => $queue[$nextIndex]]);
            Log::info('GenerateLandingPageJob: pays avancé', [
                'audience_type' => $audienceType,
                'from'          => $countryCode,
                'to'            => $queue[$nextIndex],
            ]);
        } else {
            // Toute la queue est traitée
            $campaign->update([
                'status'          => 'completed',
                'completed_at'    => now(),
                'current_country' => null,
            ]);
            Log::info('GenerateLandingPageJob: campagne terminée', [
                'audience_type' => $audienceType,
            ]);
        }
    }

    /**
     * Crée un PublicationQueueItem et dispatche PublishContentJob pour auto-publier
     * la landing page vers le blog dès sa génération.
     */
    private function autoPublish(LandingPage $landing): void
    {
        // Passer en 'review' pour signaler que la LP est prête à être publiée
        $landing->update(['status' => 'review']);

        // Chercher l'endpoint dédié aux Landing Pages, sinon l'endpoint par défaut
        $endpoint = PublishingEndpoint::where('name', 'Landing Pages SOS-Expat')
            ->where('is_active', true)
            ->first()
            ?? PublishingEndpoint::where('is_default', true)
                ->where('is_active', true)
                ->first();

        if (! $endpoint) {
            Log::warning('GenerateLandingPageJob: aucun endpoint de publication actif trouvé', [
                'landing_id' => $landing->id,
            ]);
            return;
        }

        $queueItem = PublicationQueueItem::create([
            'publishable_type' => LandingPage::class,
            'publishable_id'   => $landing->id,
            'endpoint_id'      => $endpoint->id,
            'status'           => 'pending',
            'priority'         => 'default',
            'max_attempts'     => 5,
            'attempts'         => 0,
        ]);

        PublishContentJob::dispatch($queueItem->id);

        Log::info('GenerateLandingPageJob: auto-publish dispatched', [
            'landing_id'    => $landing->id,
            'queue_item_id' => $queueItem->id,
            'endpoint'      => $endpoint->name,
        ]);
    }
}
