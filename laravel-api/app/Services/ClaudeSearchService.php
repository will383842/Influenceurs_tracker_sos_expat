<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Claude API — the "brain" of the research engine.
 *
 * NEW ROLE (post-fusion):
 * Claude NO LONGER searches for contacts (it invents them).
 * Instead, Claude:
 * 1. Cleans and structures raw Perplexity results
 * 2. Evaluates contact quality and relevance for SOS-Expat
 * 3. Scores data reliability (has real email? real URL? verified source?)
 * 4. Fills gaps by reasoning about the data
 *
 * The actual web search is done by PerplexitySearchService.
 */
class ClaudeSearchService
{
    private string $apiKey;
    private string $model;

    public function __construct()
    {
        $this->apiKey = config('services.anthropic.api_key', '');
        $this->model = config('services.anthropic.model', 'claude-sonnet-4-20250514');
    }

    /**
     * Analyze and structure raw Perplexity results.
     * Claude cleans, deduplicates, and scores each contact.
     */
    public function analyzeAndStructure(string $rawPerplexityText, string $contactType, string $country, array $citations = []): array
    {
        $citationContext = '';
        if (!empty($citations)) {
            $citationContext = "\n\nSources web trouvées par la recherche :\n" . implode("\n", array_slice($citations, 0, 20));
        }

        $systemPrompt = <<<PROMPT
Tu es un expert en data cleaning pour un CRM de prospection B2B.

Tu reçois des résultats BRUTS d'une recherche web (Perplexity). Ta mission :

1. EXTRAIRE chaque contact et le formater en blocs structurés :
   NOM: [nom exact trouvé]
   EMAIL: [email exact OU "NON TROUVÉ"]
   TEL: [téléphone exact OU "NON TROUVÉ"]
   URL: [URL exacte du profil/site]
   PLATEFORME: [youtube/instagram/tiktok/linkedin/website/blog/facebook/x]
   ABONNES: [nombre si mentionné OU "INCONNU"]
   SOURCE: [URL de la page web source si disponible]
   FIABILITE: [1-5] (voir critères ci-dessous)
   RAISON_FIABILITE: [explication courte]

2. SCORER la fiabilité de 1 à 5 :
   5 = Email vérifié + URL fonctionnelle + source citée
   4 = URL fonctionnelle + email trouvé mais non vérifié
   3 = URL fonctionnelle mais email non trouvé
   2 = Nom et plateforme mais URL incertaine
   1 = Données partielles, forte probabilité d'approximation

3. RÈGLES :
   - Si l'email n'est pas dans les données → écrire "NON TROUVÉ" (ne pas inventer)
   - Si le téléphone n'est pas mentionné → écrire "NON TROUVÉ"
   - Garder les URLs telles quelles
   - Supprimer les doublons évidents

4. IMPORTANT : GARDE TOUS LES CONTACTS trouvés, même ceux avec des données partielles (juste un nom + URL suffit).
   Ne supprime PAS un contact parce qu'il manque un email ou un téléphone.
   L'utilisateur décidera lui-même ce qui est pertinent.
PROMPT;

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 4000,
                'system'     => $systemPrompt,
                'messages'   => [
                    ['role' => 'user', 'content' => "Voici les résultats bruts de la recherche web pour des {$contactType} en {$country} :\n\n{$rawPerplexityText}{$citationContext}\n\nAnalyse et structure chaque contact trouvé."],
                ],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $text = $data['content'][0]['text'] ?? '';
                $tokens = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

                return [
                    'success' => true,
                    'text'    => $text,
                    'tokens'  => $tokens,
                ];
            }

            Log::warning('Claude analyze error', ['status' => $response->status()]);
            return ['success' => false, 'text' => '', 'tokens' => 0, 'error' => 'HTTP ' . $response->status()];

        } catch (\Throwable $e) {
            Log::error('Claude analyze exception', ['message' => $e->getMessage()]);
            return ['success' => false, 'text' => '', 'tokens' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Fallback: if Perplexity is not configured, Claude does its best alone.
     * Results will have low reliability scores.
     */
    public function searchAlone(string $prompt): array
    {
        $system = "Tu es un expert en prospection B2B. IMPORTANT : tu n'as PAS accès au web. Tes connaissances datent de ta formation. Pour chaque contact :\n\nNOM: ...\nEMAIL: NON TROUVÉ (tu ne peux pas vérifier)\nTEL: NON TROUVÉ\nURL: [URL probable basée sur tes connaissances]\nPLATEFORME: ...\nABONNES: [estimation]\nFIABILITE: 2\nRAISON_FIABILITE: Données issues de mémoire IA, non vérifiées sur le web\n\nSois honnête sur ce que tu sais vs ce que tu devines.";

        try {
            $response = Http::withHeaders([
                'x-api-key'         => $this->apiKey,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
                'model'      => $this->model,
                'max_tokens' => 4000,
                'system'     => $system,
                'messages'   => [['role' => 'user', 'content' => $prompt]],
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'success' => true,
                    'text'    => $data['content'][0]['text'] ?? '',
                    'tokens'  => ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0),
                ];
            }

            return ['success' => false, 'text' => '', 'tokens' => 0];
        } catch (\Throwable $e) {
            return ['success' => false, 'text' => '', 'tokens' => 0, 'error' => $e->getMessage()];
        }
    }
}
