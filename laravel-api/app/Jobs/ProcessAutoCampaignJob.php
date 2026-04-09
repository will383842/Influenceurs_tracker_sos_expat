<?php

namespace App\Jobs;

use App\Http\Controllers\InfluenceurController;
use App\Models\ActivityLog;
use App\Models\AiResearchSession;
use App\Models\AutoCampaign;
use App\Models\AutoCampaignTask;
use App\Models\Influenceur;
use App\Models\Directory;
use App\Services\AiPromptService;
use App\Services\BlockedDomainService;
use App\Services\ClaudeSearchService;
use App\Services\PerplexitySearchService;
use App\Services\ResultParserService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrator job: runs every minute via scheduler.
 *
 * DEFAULT: Perplexity direct (no Claude) — fast & cheap.
 * Auto-campaigns never use Claude (cost optimization).
 */
class ProcessAutoCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 240; // 4 min
    public int $tries = 1;

    public function handle(
        AiPromptService $promptService,
        PerplexitySearchService $perplexityService,
        ClaudeSearchService $claudeService,
        ResultParserService $parserService,
    ): void {
        // Auto-resume perpetual campaigns that are ready
        $perpetualReady = AutoCampaign::where('auto_restart', true)
            ->where('status', 'paused')
            ->whereNotNull('started_at')
            ->where('started_at', '<=', now())
            ->first();

        if ($perpetualReady) {
            $perpetualReady->update([
                'status'     => 'running',
                'started_at' => now(),
            ]);
            Log::info('AutoCampaign: perpetual campaign auto-resumed', [
                'campaign_id'      => $perpetualReady->id,
                'cycles_completed' => $perpetualReady->cycles_completed,
            ]);
        }

        // Find the active running campaign
        $campaign = AutoCampaign::running()->first();
        if (!$campaign) {
            return;
        }

        // Check rate limit
        if (!$campaign->isReadyForNextTask()) {
            Log::debug('AutoCampaign: not ready for next task (rate limit or circuit breaker)', [
                'campaign_id'          => $campaign->id,
                'consecutive_failures' => $campaign->consecutive_failures,
                'last_task_at'         => $campaign->last_task_at?->toIso8601String(),
            ]);
            return;
        }

        // Pick next task: pending first, then failed eligible for retry
        $task = $campaign->tasks()
            ->readyToProcess()
            ->orderBy('priority')
            ->orderBy('id')
            ->first();

        if (!$task) {
            $campaign->checkCompletion();

            if ($campaign->status === 'completed') {
                $this->logCampaignComplete($campaign);
            }
            return;
        }

        // ============================================================
        // Execute the AI research for this task
        // ============================================================
        $task->markRunning();

        Log::info('AutoCampaign: processing task', [
            'campaign_id'  => $campaign->id,
            'task_id'      => $task->id,
            'type'         => $task->contact_type,
            'country'      => $task->country,
            'language'     => $task->language,
            'attempt'      => $task->attempt,
        ]);

        try {
            $result = $this->executeResearch(
                $task, $campaign,
                $promptService, $perplexityService, $claudeService, $parserService
            );

            // Record success
            $task->markCompleted(
                $result['contacts_found'],
                $result['contacts_imported'],
                $result['session_id']
            );

            $campaign->recordTaskSuccess(
                $result['contacts_found'],
                $result['contacts_imported'],
                $result['cost_cents']
            );

            // Alert if nothing found
            if ($result['contacts_found'] === 0) {
                $this->logAlert($campaign, $task, 'no_results',
                    "Aucun contact trouvé pour {$task->contact_type} / {$task->country} (tentative {$task->attempt})"
                );
            }

            Log::info('AutoCampaign: task completed', [
                'task_id'  => $task->id,
                'found'    => $result['contacts_found'],
                'imported' => $result['contacts_imported'],
            ]);

        } catch (\Throwable $e) {
            $task->markFailed($e->getMessage(), $campaign->max_retries);
            $campaign->recordTaskFailure();

            Log::error('AutoCampaign: task failed', [
                'task_id' => $task->id,
                'attempt' => $task->attempt,
                'error'   => mb_substr($e->getMessage(), 0, 500),
            ]);

            if ($campaign->status === 'paused') {
                $this->logAlert($campaign, $task, 'circuit_breaker',
                    "Campagne en pause automatique après {$campaign->consecutive_failures} échecs consécutifs. Dernière erreur: " . mb_substr($e->getMessage(), 0, 200)
                );
            }

            if (!$task->canRetry($campaign->max_retries)) {
                $this->logAlert($campaign, $task, 'max_retries',
                    "Échec définitif pour {$task->contact_type} / {$task->country} après {$task->attempt} tentatives: " . mb_substr($e->getMessage(), 0, 200)
                );
            }
        }

        $campaign->checkCompletion();
        if ($campaign->status === 'completed') {
            $this->logCampaignComplete($campaign);
        }
    }

    /**
     * Execute the full AI research pipeline for one task.
     * DEFAULT: Perplexity direct → PHP parser (no Claude).
     */
    private function executeResearch(
        AutoCampaignTask $task,
        AutoCampaign $campaign,
        AiPromptService $promptService,
        PerplexitySearchService $perplexityService,
        ClaudeSearchService $claudeService,
        ResultParserService $parserService,
    ): array {
        $contactType = $task->contact_type;
        $country = $task->country;
        $language = $task->language;

        $session = AiResearchSession::create([
            'user_id'      => $campaign->created_by,
            'contact_type' => \App\Enums\ContactType::tryFrom($contactType)?->value ?? $contactType,
            'country'      => $country,
            'language'     => $language,
            'status'       => 'pending',
        ]);
        $session->markRunning();

        // Collect existing URLs to exclude duplicates — ALL types for this country
        // (prevents paying API to rediscover sites already known under a different type)
        $existingDomains = Influenceur::where('country', $country)
            ->whereNotNull('profile_url_domain')
            ->pluck('profile_url_domain')
            ->unique()
            ->toArray();

        $session->update(['excluded_domains' => $existingDomains]);

        // Build the search prompt
        $prompt = $promptService->buildPrompt($contactType, $country, $language, $existingDomains);

        $totalTokens = 0;
        $perplexityTokens = 0;
        $rawTexts = [];

        // ============================================================
        // Perplexity — Real web search (direct mode, no Claude)
        // ============================================================
        if ($perplexityService->isConfigured()) {
            $deepPrompt = $prompt . "\n\nCette deuxième recherche doit trouver des résultats COMPLÉMENTAIRES que la première aurait manqués. Cherche dans des sources différentes : annuaires d'expatriés, forums, groupes Facebook, blogs d'expats, pages jaunes locales, Google Maps. Visite les pages Contact de chaque site trouvé pour extraire emails et téléphones.";

            $perplexityResults = $perplexityService->searchParallel($prompt, $deepPrompt, $language);
            $perplexityTokens = $perplexityResults['tokens'];
            $totalTokens += $perplexityTokens;

            $session->update([
                'perplexity_response' => $perplexityResults['responses']['discovery'] ?? '',
                'tavily_response'     => $perplexityResults['responses']['deep'] ?? '',
            ]);

            // Parse Perplexity output directly (no Claude)
            $combinedPerplexity = trim(
                ($perplexityResults['responses']['discovery'] ?? '') . "\n\n---\n\n" .
                ($perplexityResults['responses']['deep'] ?? '')
            );

            if (!empty($combinedPerplexity)) {
                $rawTexts[] = $combinedPerplexity;
            }

            $session->update([
                'claude_response' => '[AUTO-CAMPAIGN] Direct Perplexity parsing, Claude not used.',
            ]);
        } else {
            // Fallback: Claude alone
            $claudeResult = $claudeService->searchAlone($prompt);
            if ($claudeResult['success']) {
                $rawTexts[] = $claudeResult['text'];
                $totalTokens += $claudeResult['tokens'];
            }

            $session->update([
                'claude_response'     => $claudeResult['text'] ?? '',
                'perplexity_response' => '[AUTO-CAMPAIGN] Perplexity not configured.',
            ]);
        }

        // ============================================================
        // Parse + Deduplicate
        // ============================================================
        $parsedContacts = $parserService->parseAndMerge($rawTexts, $contactType, $country);
        $deduped = $parserService->checkDuplicates($parsedContacts);

        // Add normalized profile_url_domain
        foreach ($deduped['new'] as &$contact) {
            if (!empty($contact['profile_url'])) {
                $contact['profile_url_domain'] = InfluenceurController::normalizeProfileUrl($contact['profile_url']);
            }
        }

        // ============================================================
        // Auto-import
        // ============================================================
        $imported = 0;
        $nonSocialTypes = Influenceur::NON_SOCIAL_TYPES;
        $directoriesFound = 0;

        foreach ($deduped['new'] as $contact) {
            try {
                // === INTERCEPT: Redirect directory URLs to directories table ===
                $profileUrl = $contact['profile_url'] ?? null;
                if (BlockedDomainService::isScrapableDirectory($profileUrl)) {
                    $domain = Directory::extractDomain($profileUrl);
                    $existingDir = Directory::where('url', $profileUrl)
                        ->where('category', $contactType)
                        ->first();

                    if (!$existingDir) {
                        $dir = Directory::create([
                            'name'       => $contact['name'] ?? 'Annuaire ' . $domain,
                            'url'        => $profileUrl,
                            'domain'     => $domain,
                            'category'   => $contactType,
                            'country'    => $contact['country'] ?? $country,
                            'language'   => $language,
                            'status'     => 'pending',
                            'notes'      => 'Auto-détecté depuis campagne IA',
                            'created_by' => $campaign->created_by,
                        ]);
                        ScrapeDirectoryJob::dispatch($dir->id);
                        $directoriesFound++;
                        Log::info('AutoCampaign: directory URL intercepted → directories table', [
                            'url'    => $profileUrl,
                            'domain' => $domain,
                            'dir_id' => $dir->id,
                        ]);
                    }
                    continue;
                }

                // Duplicate checks
                $nameExists = Influenceur::whereRaw('LOWER(name) = ?', [strtolower($contact['name'])])
                    ->where('country', $country)
                    ->exists();
                if ($nameExists) continue;

                if (!empty($contact['profile_url_domain'])) {
                    if (Influenceur::where('profile_url_domain', $contact['profile_url_domain'])->exists()) continue;
                }
                if (!empty($contact['email'])) {
                    if (Influenceur::where('email', strtolower($contact['email']))->exists()) continue;
                }

                // Website URL for non-social types
                $websiteUrl = null;
                if (in_array($contactType, $nonSocialTypes) && !empty($contact['profile_url'])) {
                    $websiteUrl = $contact['profile_url'];
                }

                // Ensure profile_url_domain is set (critical for dedup across campaigns)
                $profileUrlDomain = $contact['profile_url_domain'] ?? null;
                if (!$profileUrlDomain && !empty($contact['profile_url'])) {
                    $host = parse_url($contact['profile_url'], PHP_URL_HOST);
                    if ($host) {
                        $profileUrlDomain = preg_replace('/^www\./', '', $host);
                    }
                }

                $influenceur = Influenceur::create([
                    'contact_type'       => $contact['contact_type'] ?? $contactType,
                    'name'               => $contact['name'],
                    'email'              => $contact['email'] ?? null,
                    'phone'              => $contact['phone'] ?? null,
                    'profile_url'        => $contact['profile_url'] ?? null,
                    'profile_url_domain' => $profileUrlDomain ?? $contact['profile_url_domain'] ?? null,
                    'website_url'        => $websiteUrl,
                    'country'            => $contact['country'] ?? $country,
                    'language'           => $language,
                    'platforms'          => $contact['platforms'] ?? [],
                    'primary_platform'   => $contact['platforms'][0] ?? 'website',
                    'followers'          => $contact['followers'] ?? null,
                    'notes'              => $contact['notes'] ?? null,
                    'source'             => 'auto_campaign',
                    'status'             => 'new',
                    'score'              => ($contact['reliability_score'] ?? 1) * 20,
                    'created_by'         => $campaign->created_by,
                ]);

                // Auto-dispatch scraping
                if (!empty($websiteUrl) || !empty($contact['profile_url'])) {
                    ScrapeContactJob::dispatch($influenceur->id);
                }

                $imported++;
            } catch (\Throwable $e) {
                Log::debug('AutoCampaign: import failed', ['name' => $contact['name'] ?? '?', 'error' => $e->getMessage()]);
            }
        }

        // Finalize session
        $costCents = $this->estimateCost($perplexityTokens, $perplexityService->isConfigured());
        $session->update([
            'status'              => 'completed',
            'completed_at'        => now(),
            'parsed_contacts'     => $deduped['new'],
            'contacts_found'      => count($parsedContacts),
            'contacts_imported'   => $imported,
            'contacts_duplicates' => count($deduped['duplicates']),
            'tokens_used'         => $totalTokens,
            'cost_cents'          => $costCents,
        ]);

        return [
            'contacts_found'    => count($parsedContacts),
            'contacts_imported' => $imported,
            'cost_cents'        => $costCents,
            'session_id'        => $session->id,
        ];
    }

    private function logAlert(AutoCampaign $campaign, AutoCampaignTask $task, string $type, string $message): void
    {
        ActivityLog::create([
            'user_id'      => $campaign->created_by,
            'action'        => 'auto_campaign_alert',
            'contact_type'  => $task->contact_type,
            'details'       => [
                'campaign_id'   => $campaign->id,
                'campaign_name' => $campaign->name,
                'task_id'       => $task->id,
                'alert_type'    => $type,
                'country'       => $task->country,
                'language'      => $task->language,
                'message'       => $message,
            ],
        ]);
    }

    private function logCampaignComplete(AutoCampaign $campaign): void
    {
        ActivityLog::create([
            'user_id' => $campaign->created_by,
            'action'   => 'auto_campaign_completed',
            'details'  => [
                'campaign_id'      => $campaign->id,
                'campaign_name'    => $campaign->name,
                'tasks_completed'  => $campaign->tasks_completed,
                'tasks_failed'     => $campaign->tasks_failed,
                'tasks_skipped'    => $campaign->tasks_skipped,
                'contacts_found'   => $campaign->contacts_found_total,
                'contacts_imported' => $campaign->contacts_imported_total,
                'total_cost_cents' => $campaign->total_cost_cents,
                'duration_minutes' => $campaign->started_at
                    ? (int) $campaign->started_at->diffInMinutes(now())
                    : null,
            ],
        ]);

        Log::info('AutoCampaign: COMPLETED', [
            'campaign_id' => $campaign->id,
            'name'        => $campaign->name,
            'imported'    => $campaign->contacts_imported_total,
            'cost_cents'  => $campaign->total_cost_cents,
        ]);
    }

    /**
     * Estimate API cost in cents (Perplexity only, no Claude).
     * Perplexity sonar: ~$1/M tokens + $5/1000 searches
     */
    private function estimateCost(int $perplexityTokens, bool $usedPerplexity): int
    {
        if (!$usedPerplexity) return 0;

        // ~$1/M tokens = 0.1 cents/1K tokens + ~$0.01 for 2 parallel searches
        return (int) round($perplexityTokens * 0.0001) + 1;
    }
}
