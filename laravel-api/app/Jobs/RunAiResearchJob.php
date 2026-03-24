<?php

namespace App\Jobs;

use App\Http\Controllers\InfluenceurController;
use App\Models\AiResearchSession;
use App\Models\Influenceur;
use App\Services\AiPromptService;
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
 * AI Research Pipeline:
 *
 * DEFAULT MODE (fast & cheap):
 *   Perplexity (structured output) → PHP Parser → Deduplicate → Import
 *
 * QUALITY MODE (use_claude = true):
 *   Perplexity → Claude (clean + score) → PHP Parser → Deduplicate → Import
 *
 * FALLBACK (no Perplexity key):
 *   Claude alone (low reliability)
 */
class RunAiResearchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 180; // 3 min
    public int $tries = 2;

    public function __construct(
        private int $sessionId,
        private ?string $customPrompt = null,
        private bool $useClaude = false,
    ) {}

    public function handle(
        AiPromptService $promptService,
        PerplexitySearchService $perplexityService,
        ClaudeSearchService $claudeService,
        ResultParserService $parserService,
    ): void {
        $session = AiResearchSession::find($this->sessionId);
        if (!$session || $session->status !== 'pending') return;

        $session->markRunning();

        try {
            $contactType = $session->contact_type instanceof \App\Enums\ContactType
                ? $session->contact_type->value
                : $session->contact_type;

            // 1. Collect existing URLs to exclude
            $existingDomains = Influenceur::where('contact_type', $contactType)
                ->where('country', $session->country)
                ->whereNotNull('profile_url_domain')
                ->pluck('profile_url_domain')
                ->toArray();

            $session->update(['excluded_domains' => $existingDomains]);

            // 2. Build the search prompt (use custom if provided)
            if ($this->customPrompt) {
                $prompt = $this->customPrompt;
            } else {
                $prompt = $promptService->buildPrompt(
                    $contactType,
                    $session->country,
                    $session->language,
                    $existingDomains
                );
            }

            $totalTokens = 0;
            $perplexityTokens = 0;
            $claudeTokens = 0;
            $rawTexts = [];

            // ============================================================
            // STEP 1: Perplexity — Real web search
            // ============================================================
            if ($perplexityService->isConfigured()) {
                Log::info('AI Research: Using Perplexity' . ($this->useClaude ? ' + Claude' : ' direct'), ['session' => $session->id]);

                // Two Perplexity searches in parallel
                $deepPrompt = $prompt . "\n\nCette deuxième recherche doit trouver des résultats COMPLÉMENTAIRES que la première aurait manqués. Cherche dans des sources différentes : annuaires d'expatriés, forums, groupes Facebook, blogs d'expats, pages jaunes locales, Google Maps. Visite les pages Contact de chaque site trouvé pour extraire emails et téléphones.";

                $perplexityResults = $perplexityService->searchParallel($prompt, $deepPrompt, $session->language);
                $perplexityTokens = $perplexityResults['tokens'];
                $totalTokens += $perplexityTokens;

                // Store raw Perplexity responses
                $session->update([
                    'perplexity_response' => $perplexityResults['responses']['discovery'] ?? '',
                    'tavily_response'     => $perplexityResults['responses']['deep'] ?? '',
                ]);

                // Merge both Perplexity responses
                $combinedPerplexity = trim(
                    ($perplexityResults['responses']['discovery'] ?? '') . "\n\n---\n\n" .
                    ($perplexityResults['responses']['deep'] ?? '')
                );

                if ($this->useClaude && !empty($combinedPerplexity)) {
                    // ============================================================
                    // QUALITY MODE: Claude analyzes and structures Perplexity results
                    // ============================================================
                    $claudeResult = $claudeService->analyzeAndStructure(
                        $combinedPerplexity,
                        $contactType,
                        $session->country,
                        $perplexityResults['citations'] ?? []
                    );

                    if ($claudeResult['success']) {
                        $rawTexts[] = $claudeResult['text'];
                        $claudeTokens = $claudeResult['tokens'];
                        $totalTokens += $claudeTokens;
                    }

                    $session->update([
                        'claude_response' => $claudeResult['text'] ?? '',
                    ]);
                } else {
                    // ============================================================
                    // DEFAULT MODE: Parse Perplexity output directly
                    // ============================================================
                    if (!empty($combinedPerplexity)) {
                        $rawTexts[] = $combinedPerplexity;
                    }

                    $session->update([
                        'claude_response' => '[DIRECT MODE] Perplexity parsed directly, Claude not used.',
                    ]);
                }
            } else {
                // ============================================================
                // FALLBACK: Claude alone (no Perplexity)
                // ============================================================
                Log::info('AI Research: Perplexity not configured, using Claude fallback', ['session' => $session->id]);

                $claudeResult = $claudeService->searchAlone($prompt);
                if ($claudeResult['success']) {
                    $rawTexts[] = $claudeResult['text'];
                    $claudeTokens = $claudeResult['tokens'];
                    $totalTokens += $claudeTokens;
                }

                $session->update([
                    'claude_response'     => $claudeResult['text'] ?? '',
                    'perplexity_response' => '[NOT CONFIGURED] Perplexity API key missing. Using Claude fallback with low reliability.',
                ]);
            }

            // ============================================================
            // STEP 2: Parse + Deduplicate
            // ============================================================
            $parsedContacts = $parserService->parseAndMerge($rawTexts, $contactType, $session->country);
            $deduped = $parserService->checkDuplicates($parsedContacts);

            // Add normalized profile_url_domain
            foreach ($deduped['new'] as &$contact) {
                if (!empty($contact['profile_url'])) {
                    $contact['profile_url_domain'] = InfluenceurController::normalizeProfileUrl($contact['profile_url']);
                }
            }

            // ============================================================
            // STEP 3: AUTO-IMPORT all new contacts into influenceurs table
            // ============================================================
            $imported = 0;
            $skippedDuplicates = 0;
            foreach ($deduped['new'] as $contact) {
                try {
                    // Check duplicate by name + country (case-insensitive)
                    $nameExists = Influenceur::whereRaw('LOWER(name) = ?', [strtolower($contact['name'])])
                        ->where('country', $session->country)
                        ->exists();
                    if ($nameExists) {
                        $skippedDuplicates++;
                        Log::debug('Auto-import: skipped name duplicate', ['name' => $contact['name']]);
                        continue;
                    }

                    // Check duplicate by profile_url_domain (if we have one)
                    if (!empty($contact['profile_url_domain'])) {
                        $urlExists = Influenceur::where('profile_url_domain', $contact['profile_url_domain'])->exists();
                        if ($urlExists) {
                            $skippedDuplicates++;
                            continue;
                        }
                    }

                    // Check duplicate by email
                    if (!empty($contact['email'])) {
                        $emailExists = Influenceur::where('email', strtolower($contact['email']))->exists();
                        if ($emailExists) {
                            $skippedDuplicates++;
                            continue;
                        }
                    }

                    // For non-social contact types, the URL is a website, not a social profile
                    $websiteUrl = null;
                    $nonSocialTypes = Influenceur::NON_SOCIAL_TYPES;
                    $effectiveType = $contact['contact_type'] ?? $contactType;
                    if (in_array($effectiveType, $nonSocialTypes) && !empty($contact['profile_url'])) {
                        $websiteUrl = $contact['profile_url'];
                    }

                    Influenceur::create([
                        'contact_type'       => $effectiveType,
                        'name'               => $contact['name'],
                        'email'              => $contact['email'] ?? null,
                        'phone'              => $contact['phone'] ?? null,
                        'profile_url'        => $contact['profile_url'] ?? null,
                        'profile_url_domain' => $contact['profile_url_domain'] ?? null,
                        'website_url'        => $websiteUrl,
                        'country'            => $contact['country'] ?? $session->country,
                        'language'           => $session->language,
                        'platforms'          => $contact['platforms'] ?? [],
                        'primary_platform'   => $contact['platforms'][0] ?? 'website',
                        'followers'          => $contact['followers'] ?? null,
                        'notes'              => $contact['notes'] ?? null,
                        'source'             => 'ai_research',
                        'status'             => 'new',
                        'score'              => ($contact['reliability_score'] ?? 1) * 20,
                        'created_by'         => $session->user_id,
                    ]);
                    $imported++;
                } catch (\Throwable $e) {
                    Log::warning('Auto-import contact failed', [
                        'name'  => $contact['name'] ?? 'unknown',
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // ============================================================
            // DONE — Save results
            // ============================================================
            $session->update([
                'status'              => 'completed',
                'completed_at'        => now(),
                'parsed_contacts'     => $deduped['new'],
                'contacts_found'      => count($parsedContacts),
                'contacts_imported'   => $imported,
                'contacts_duplicates' => count($deduped['duplicates']),
                'tokens_used'         => $totalTokens,
                'cost_cents'          => $this->estimateCost($perplexityTokens, $claudeTokens, $perplexityService->isConfigured()),
            ]);

            Log::info('AI Research completed + auto-imported', [
                'session_id'   => $session->id,
                'pipeline'     => $this->useClaude ? 'perplexity+claude' : ($perplexityService->isConfigured() ? 'perplexity-direct' : 'claude-only'),
                'found'        => count($parsedContacts),
                'new'          => count($deduped['new']),
                'imported'     => $imported,
                'duplicates'   => count($deduped['duplicates']),
                'tokens'       => $totalTokens,
            ]);

        } catch (\Throwable $e) {
            $session->markFailed($e->getMessage());
            Log::error('AI Research failed', [
                'session_id' => $session->id,
                'error'      => $e->getMessage(),
                'trace'      => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Estimate API cost in cents.
     *
     * Perplexity sonar: ~$1/M input + $1/M output + $5/1000 searches
     *   → ~$0.005 per search + tokens ≈ $0.012 for 2 parallel searches
     * Claude Sonnet: ~$3/M input + $15/M output → blended ~$9/M
     */
    private function estimateCost(int $perplexityTokens, int $claudeTokens, bool $usedPerplexity): int
    {
        // Perplexity: ~$1/M tokens = 0.1 cents/1K tokens + $0.005 per search
        $perplexityCost = $usedPerplexity
            ? (int) round($perplexityTokens * 0.0001) + 1  // tokens + ~$0.01 for 2 searches
            : 0;

        // Claude: ~$9/M tokens = 0.9 cents/1K tokens
        $claudeCost = (int) round($claudeTokens * 0.0009);

        return $perplexityCost + $claudeCost;
    }
}
