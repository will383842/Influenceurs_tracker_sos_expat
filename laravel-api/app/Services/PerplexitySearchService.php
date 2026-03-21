<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Perplexity API (sonar) — searches the REAL web in real-time.
 * This is the "eyes" of the research engine.
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
     * Search the web for real contacts.
     */
    public function search(string $query, string $language = 'fr'): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'text' => '', 'citations' => [], 'error' => 'API key not configured'];
        }

        // System prompt: ENCOURAGE results, don't block them
        $systemPrompt = "Tu es un assistant de recherche web. Ton rôle est de trouver un MAXIMUM de contacts pertinents. "
            . "Pour chaque contact trouvé, donne : NOM, EMAIL (si trouvé sur le web, sinon écris 'non trouvé'), TEL (si trouvé, sinon 'non trouvé'), URL (lien vers le site/profil), SOURCE (page web source). "
            . "IMPORTANT : donne TOUS les résultats que tu trouves, même si l'email ou le téléphone manque. Un contact avec juste un nom et une URL est utile. "
            . "Ne filtre PAS les résultats — c'est l'utilisateur qui décidera lesquels sont pertinents.";

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
                'max_tokens'       => 4000,
                'temperature'      => 0.5,  // Medium: balanced between creative and factual
                'return_citations' => true,
                // NO search_recency_filter — search ALL time periods
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['choices'][0]['message']['content'] ?? '';
                $citations = $data['citations'] ?? [];
                $tokens = ($data['usage']['prompt_tokens'] ?? 0) + ($data['usage']['completion_tokens'] ?? 0);

                Log::info('Perplexity search OK', [
                    'text_length' => strlen($text),
                    'citations'   => count($citations),
                    'tokens'      => $tokens,
                ]);

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
            Log::error('Perplexity exception', ['message' => $e->getMessage()]);
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

        $system = "Tu es un assistant de recherche web. Trouve un MAXIMUM de contacts. "
            . "Pour chaque : NOM, EMAIL (ou 'non trouvé'), TEL (ou 'non trouvé'), URL, SOURCE. "
            . "Donne TOUS les résultats, même partiels. Ne filtre rien.";

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
                        'temperature' => 0.5,
                        'return_citations' => true,
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
                        'temperature' => 0.7, // More creative for deep search
                        'return_citations' => true,
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

            Log::info('Perplexity parallel OK', [
                'discovery_len' => strlen($results['discovery']),
                'deep_len'      => strlen($results['deep']),
                'citations'     => count($allCitations),
            ]);

            return ['responses' => $results, 'citations' => array_unique($allCitations), 'tokens' => $totalTokens];

        } catch (\Throwable $e) {
            Log::error('Perplexity parallel failed', ['error' => $e->getMessage()]);
            return ['responses' => ['discovery' => '', 'deep' => ''], 'citations' => [], 'tokens' => 0];
        }
    }
}
