<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Perplexity Sonar API — recherche web temps réel.
 * Utilisé pour la découverte de contacts (YouTubeurs, journalistes, etc.)
 */
class PerplexityService
{
    private string $apiKey;
    private string $model;

    private const API_URL = 'https://api.perplexity.ai/chat/completions';
    private const TIMEOUT = 60;

    public function __construct()
    {
        $this->apiKey = config('services.perplexity.api_key', '');
        $this->model  = config('services.perplexity.model', 'sonar');
    }

    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Recherche via Perplexity et retourne le texte brut de la réponse.
     */
    public function search(string $query, string $systemPrompt = ''): array
    {
        if (!$this->isConfigured()) {
            return ['success' => false, 'error' => 'Perplexity API key not configured', 'content' => ''];
        }

        $messages = [];
        if ($systemPrompt) {
            $messages[] = ['role' => 'system', 'content' => $systemPrompt];
        }
        $messages[] = ['role' => 'user', 'content' => $query];

        try {
            $response = Http::timeout(self::TIMEOUT)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type'  => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model'    => $this->model,
                    'messages' => $messages,
                ]);

            if (!$response->successful()) {
                Log::error('Perplexity API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return ['success' => false, 'error' => 'HTTP ' . $response->status(), 'content' => ''];
            }

            $data    = $response->json();
            $content = $data['choices'][0]['message']['content'] ?? '';

            return ['success' => true, 'content' => $content, 'raw' => $data];

        } catch (\Throwable $e) {
            Log::error('Perplexity exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'error' => $e->getMessage(), 'content' => ''];
        }
    }

    /**
     * Recherche structurée — demande une réponse JSON et la parse.
     */
    public function searchJson(string $query, string $systemPrompt = ''): array
    {
        $result = $this->search($query, $systemPrompt);

        if (!$result['success']) {
            return $result;
        }

        $content = $result['content'];

        // Extraire le JSON du texte (parfois entouré de markdown ```json ... ```)
        if (preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/i', $content, $m)) {
            $content = $m[1];
        } elseif (preg_match('/(\[[\s\S]*\]|\{[\s\S]*\})/s', $content, $m)) {
            $content = $m[1];
        }

        $decoded = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return ['success' => false, 'error' => 'JSON parse failed: ' . json_last_error_msg(), 'content' => $result['content']];
        }

        return ['success' => true, 'data' => $decoded, 'content' => $result['content']];
    }
}
