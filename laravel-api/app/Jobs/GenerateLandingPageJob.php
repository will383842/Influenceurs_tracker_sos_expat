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
            } else {
                Log::debug('GenerateLandingPageJob: generation_cost_cents=0, coût non tracké', [
                    'landing_id' => $landing->id,
                ]);
            }

            // Vérifier si le pays courant est terminé → avancer au suivant
            $this->advanceCountryIfComplete(
                $this->params['audience_type'],
                $this->params['country_code'],
            );

            // ── Multi-langues : dispatcher les 8 variantes si c'est la version primaire ──
            // La version primaire est identifiée par l'absence de parent_id dans les params.
            // Chaque variante hérite de l'image Unsplash de la version primaire.
            if (empty($this->params['parent_id'])) {
                $this->dispatchLanguageVariants($landing);
            }

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
    // Multi-langues
    // ──────────────────────────────────────────────────────────────

    /**
     * Dispatche GenerateLandingPageJob pour les 8 langues autres que celle du parent.
     * L'image Unsplash est héritée du parent pour éviter 8× appels API Unsplash.
     */
    private function dispatchLanguageVariants(LandingPage $parent): void
    {
        $allLanguages = ['fr', 'en', 'es', 'de', 'pt', 'ar', 'hi', 'zh', 'ru'];
        $primaryLang  = $this->params['language'] ?? 'fr';

        $delay = 10; // 10s offset initial (laisser le parent se stabiliser en DB)

        foreach ($allLanguages as $lang) {
            if ($lang === $primaryLang) {
                continue; // Déjà généré
            }

            $variantParams = array_merge($this->params, [
                'language'                   => $lang,
                'parent_id'                  => $parent->id,
                'use_cheap_model'            => true,   // GPT-4o-mini pour les 8 variantes
                // Hériter de l'image du parent — évite 8 appels Unsplash
                'featured_image_url'         => $parent->featured_image_url,
                'featured_image_alt'         => $parent->featured_image_alt,
                'featured_image_attribution' => $parent->featured_image_attribution,
                'photographer_name'          => $parent->photographer_name,
                'photographer_url'           => $parent->photographer_url,
            ]);

            self::dispatch($variantParams)
                ->delay(now()->addSeconds($delay))
                ->onQueue('landings');

            $delay += 8; // 8s entre chaque langue (anti-throttle Claude API)
        }

        Log::info('GenerateLandingPageJob: variantes langues dispatchées', [
            'parent_id'    => $parent->id,
            'primary_lang' => $primaryLang,
            'languages'    => array_diff($allLanguages, [$primaryLang]),
            'delay_range'  => "10s → {$delay}s",
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
