<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Perplexity API (sonar) — searches the REAL web in real-time.
 * This is the "eyes" of the research engine: it finds actual contacts
 * with real URLs, real emails from public web pages.
 *
 * API docs: https://docs.perplexity.ai/
 */
class PerplexitySearchService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.perplexity.api_key', '');
        $this->model = config('services.perplexity.model', 'sonar');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Search the web for real contacts using Perplexity sonar.
     * Returns structured results with REAL data from the web.
     */
    public function search(string $query, string $language = 'fr'): array
    {
        if (!$this->isConfigured()) {
            Log::warning('Perplexity API key not configured');
            return ['success' => false, 'text' => '', 'citations' => [], 'error' => 'API key not configured'];
        }

        $systemPrompt = $language === 'fr'
            ? "Tu es un assistant de recherche spécialisé en prospection B2B. Tu cherches des contacts RÉELS et VÉRIFIÉS sur le web. Pour chaque contact trouvé, tu DOIS fournir :\n- NOM: le vrai nom (personne ou organisation)\n- EMAIL: l'email RÉEL trouvé sur leur site web (pas inventé)\n- TEL: le téléphone RÉEL (pas inventé)\n- URL: le lien EXACT vers leur site ou profil\n- SOURCE: l'URL de la page web où tu as trouvé cette information\n\nSi tu ne trouves PAS un email ou téléphone sur le web, écris 'NON TROUVÉ' — ne JAMAIS inventer. La précision est plus importante que la quantité."
            : "You are a B2B prospecting research assistant. Search for REAL, VERIFIED contacts on the web. For each contact found, you MUST provide:\n- NAME: the real name\n- EMAIL: the REAL email found on their website (never invent)\n- PHONE: the REAL phone (never invent)\n- URL: the EXACT link to their site or profile\n- SOURCE: the URL where you found this information\n\nIf you can NOT find an email or phone, write 'NOT FOUND' — NEVER invent. Accuracy over quantity.";

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type'  => 'application/json',
            ])->timeout(90)->post('https://api.perplexity.ai/chat/completions', [
                'model'    => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $query],
                ],
                'max_tokens'         => 4000,
                'temperature'        => 0.1,  // Low = more factual
                'return_citations'   => true,
                'search_recency_filter' => 'year', // Last 12 months
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';
                $citations = $data['citations'] ?? [];
                $tokens = ($data['usage']['prompt_tokens'] ?? 0) + ($data['usage']['completion_tokens'] ?? 0);

                return [
                    'success'   => true,
                    'text'      => $text,
                    'citations' => $citations,
                    'tokens'    => $tokens,
                ];
            }

            Log::warning('Perplexity API error', ['status' => $response->status(), 'body' => $response->body()]);
            return ['success' => false, 'text' => '', 'citations' => [], 'error' => 'HTTP ' . $response->status()];

        } catch (\Throwable $e) {
            Log::error('Perplexity API exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'text' => '', 'citations' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Run two parallel Perplexity searches with different angles.
     */
    public function searchParallel(string $query1, string $query2, string $language = 'fr'): array
    {
        if (!$this->isConfigured()) {
            return ['responses' => ['discovery' => '', 'deep' => ''], 'citations' => [], 'tokens' => 0];
        }

        $system = $language === 'fr'
            ? "Tu es un assistant de recherche. Cherche des contacts RÉELS sur le web. Pour chaque contact : NOM:, EMAIL: (réel ou 'NON TROUVÉ'), TEL: (réel ou 'NON TROUVÉ'), URL: (lien exact), SOURCE: (où trouvé). Ne JAMAIS inventer de données."
            : "You are a research assistant. Find REAL contacts on the web. For each: NAME:, EMAIL: (real or 'NOT FOUND'), PHONE: (real or 'NOT FOUND'), URL: (exact link), SOURCE: (where found). NEVER invent data.";

        try {
            $responses = Http::pool(fn ($pool) => [
                $pool->as('discovery')
                    ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post('https://api.perplexity.ai/chat/completions', [
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $query1],
                        ],
                        'max_tokens' => 4000,
                        'temperature' => 0.1,
                        'return_citations' => true,
                        'search_recency_filter' => 'year',
                    ]),
                $pool->as('deep')
                    ->withHeaders(['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'])
                    ->timeout(90)
                    ->post('https://api.perplexity.ai/chat/completions', [
                        'model' => $this->model,
                        'messages' => [
                            ['role' => 'system', 'content' => $system],
                            ['role' => 'user', 'content' => $query2],
                        ],
                        'max_tokens' => 4000,
                        'temperature' => 0.2,
                        'return_citations' => true,
                        'search_recency_filter' => 'year',
                    ]),
            ]);

            $results = ['discovery' => '', 'deep' => ''];
            $allCitations = [];
            $totalTokens = 0;

            foreach (['discovery', 'deep'] as $key) {
                $resp = $responses[$key] ?? null;
                if ($resp && $resp->successful()) {
                    $data = $resp->json();
                    $results[$key] = $data['choices'][0]['message']['content'] ?? '';
                    $allCitations = array_merge($allCitations, $data['citations'] ?? []);
                    $totalTokens += ($data['usage']['prompt_tokens'] ?? 0) + ($data['usage']['completion_tokens'] ?? 0);
                }
            }

            return ['responses' => $results, 'citations' => array_unique($allCitations), 'tokens' => $totalTokens];

        } catch (\Throwable $e) {
            Log::error('Perplexity parallel search failed', ['error' => $e->getMessage()]);
            return ['responses' => ['discovery' => '', 'deep' => ''], 'citations' => [], 'tokens' => 0];
        }
    }
}
