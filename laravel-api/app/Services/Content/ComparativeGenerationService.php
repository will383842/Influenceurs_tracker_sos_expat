<?php

namespace App\Services\Content;

use App\Models\Comparative;
use App\Models\GenerationLog;
use App\Services\AI\OpenAiService;
use App\Services\PerplexitySearchService;
use App\Services\Seo\JsonLdService;
use App\Services\Seo\SeoAnalysisService;
use App\Services\Seo\SlugService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Comparative content generation — structured comparisons between entities.
 */
class ComparativeGenerationService
{
    public function __construct(
        private OpenAiService $openAi,
        private PerplexitySearchService $perplexity,
        private SeoAnalysisService $seoAnalysis,
        private JsonLdService $jsonLd,
        private SlugService $slugService,
    ) {}

    /**
     * Generate a comparative analysis article.
     */
    public function generate(array $params): Comparative
    {
        $startTime = microtime(true);

        $entities = $params['entities'] ?? [];
        $language = $params['language'] ?? 'fr';
        $country = $params['country'] ?? null;
        $keywords = $params['keywords'] ?? [];

        $comparative = Comparative::create([
            'uuid' => (string) Str::uuid(),
            'title' => $params['title'] ?? implode(' vs ', $entities),
            'language' => $language,
            'country' => $country,
            'entities' => $entities,
            'status' => 'generating',
            'created_by' => $params['created_by'] ?? null,
        ]);

        Log::info('Comparative generation started', [
            'comparative_id' => $comparative->id,
            'entities' => $entities,
        ]);

        try {
            // Phase 1: Research each entity
            $researchData = [];
            foreach ($entities as $entity) {
                $research = $this->researchEntity($entity, $language, $country);
                $researchData[$entity] = $research;
            }

            $this->logPhase($comparative, 'research', 'success', count($entities) . ' entities researched');

            // Phase 2: Generate comparison data (structured)
            $comparisonTable = $this->generateComparisonTable($entities, $researchData);
            $this->logPhase($comparative, 'comparison_table', 'success');

            // Phase 3: Generate pros/cons for each entity
            $comparisonData = [];
            foreach ($entities as $entity) {
                $context = $researchData[$entity]['text'] ?? '';
                $prosConsResult = $this->generateProsConsForEntity($entity, $context);
                $comparisonData[$entity] = $prosConsResult;
            }

            $comparative->update(['comparison_data' => $comparisonData]);
            $this->logPhase($comparative, 'pros_cons', 'success');

            // Phase 4: Generate full content HTML
            $contentHtml = $this->generateContentHtml($comparative, $comparisonTable, $comparisonData, $language);
            $comparative->update(['content_html' => $contentHtml]);
            $this->logPhase($comparative, 'content', 'success');

            // Phase 5: Generate meta tags
            $meta = $this->generateMeta($comparative->title, $entities, $language);
            $comparative->update([
                'meta_title' => $meta['meta_title'],
                'meta_description' => $meta['meta_description'],
                'excerpt' => $meta['excerpt'],
            ]);
            $this->logPhase($comparative, 'meta', 'success');

            // Phase 6: Generate JSON-LD
            $jsonLdData = $this->jsonLd->generateComparativeSchema($comparative->fresh());
            $comparative->update(['json_ld' => $jsonLdData]);
            $this->logPhase($comparative, 'json_ld', 'success');

            // Phase 7: Generate slug and calculate quality
            $slug = $this->slugService->generateSlug($comparative->title, $language);
            $slug = $this->slugService->ensureUnique($slug, $language, 'comparatives', $comparative->id);
            $comparative->update(['slug' => $slug]);

            $seoResult = $this->seoAnalysis->analyze($comparative->fresh());
            $comparative->update([
                'quality_score' => $seoResult->overall_score,
                'status' => 'review',
            ]);

            $totalDuration = (int) ((microtime(true) - $startTime) * 1000);
            Log::info('Comparative generation complete', [
                'comparative_id' => $comparative->id,
                'duration_ms' => $totalDuration,
            ]);

            return $comparative->fresh();
        } catch (\Throwable $e) {
            Log::error('Comparative generation failed', [
                'comparative_id' => $comparative->id,
                'message' => $e->getMessage(),
            ]);

            $comparative->update(['status' => 'draft']);
            $this->logPhase($comparative, 'pipeline', 'error', $e->getMessage());

            return $comparative->fresh();
        }
    }

    /**
     * Research a single entity via Perplexity.
     */
    private function researchEntity(string $entity, string $language, ?string $country): array
    {
        if (!$this->perplexity->isConfigured()) {
            return ['text' => '', 'citations' => []];
        }

        $countryContext = $country ? " en {$country}" : '';
        $query = "Recherche des informations factuelles sur \"{$entity}\"{$countryContext}: "
            . "avantages, inconvénients, tarifs, fonctionnalités principales, avis utilisateurs.";

        $result = $this->perplexity->search($query, $language);

        return [
            'text' => $result['text'] ?? '',
            'citations' => $result['citations'] ?? [],
        ];
    }

    /**
     * Generate a structured comparison table.
     *
     * @return array<array{criteria: string, values: array<string, string>}>
     */
    private function generateComparisonTable(array $entities, array $researchData): array
    {
        $entitiesStr = implode(', ', $entities);
        $researchContext = '';
        foreach ($researchData as $entity => $data) {
            $text = $data['text'] ?? '';
            if (!empty($text)) {
                $researchContext .= "\n\n{$entity}:\n" . mb_substr($text, 0, 1000);
            }
        }

        $systemPrompt = "Tu es un analyste comparatif. Compare les entités suivantes selon des critères pertinents. "
            . "Retourne en JSON un tableau de comparaison:\n"
            . "[{\"criteria\": \"Nom du critère\", \"values\": {\"Entity1\": \"valeur\", \"Entity2\": \"valeur\"}}]\n\n"
            . "Inclus 8-12 critères pertinents (prix, fonctionnalités, facilité d'utilisation, support, etc.).";

        $result = $this->openAi->complete($systemPrompt,
            "Entités à comparer: {$entitiesStr}\n\nDonnées de recherche:{$researchContext}", [
                'temperature' => 0.5,
                'max_tokens' => 2000,
                'json_mode' => true,
            ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            // Handle various JSON structures
            return $parsed['comparison'] ?? $parsed['table'] ?? $parsed['criteria'] ?? (is_array($parsed) && isset($parsed[0]) ? $parsed : []);
        }

        return [];
    }

    /**
     * Generate pros, cons, and rating for a single entity.
     *
     * @return array{pros: array, cons: array, rating: float}
     */
    private function generateProsConsForEntity(string $entity, string $context): array
    {
        $contextExcerpt = mb_substr($context, 0, 1500);

        $systemPrompt = "Analyse l'entité suivante et retourne en JSON:\n"
            . "{\"pros\": [\"avantage 1\", ...], \"cons\": [\"inconvénient 1\", ...], \"rating\": 4.2}\n\n"
            . "3-6 avantages, 2-4 inconvénients, note sur 5. Sois objectif et factuel.";

        $result = $this->openAi->complete($systemPrompt,
            "Entité: {$entity}\n\nContexte:\n{$contextExcerpt}", [
                'temperature' => 0.5,
                'max_tokens' => 800,
                'json_mode' => true,
            ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            return [
                'pros' => $parsed['pros'] ?? [],
                'cons' => $parsed['cons'] ?? [],
                'rating' => (float) ($parsed['rating'] ?? 3.0),
            ];
        }

        return ['pros' => [], 'cons' => [], 'rating' => 3.0];
    }

    /**
     * Generate the full HTML content including comparison table.
     */
    private function generateContentHtml(Comparative $comparative, array $comparisonTable, array $comparisonData, string $language): string
    {
        $entities = $comparative->entities ?? [];
        $title = $comparative->title;

        // Build comparison table HTML
        $tableHtml = $this->buildComparisonTableHtml($comparisonTable, $entities);

        // Build pros/cons sections
        $prosConsHtml = '';
        foreach ($entities as $entity) {
            $data = $comparisonData[$entity] ?? [];
            $prosConsHtml .= $this->buildProsConsHtml($entity, $data);
        }

        $systemPrompt = "Tu es un rédacteur web expert en comparatifs. Rédige un article comparatif complet en HTML. "
            . "Langue: {$language}.\n\n"
            . "STRUCTURE ATTENDUE:\n"
            . "- Introduction (2-3 paragraphes)\n"
            . "- Le tableau de comparaison est déjà fourni (intègre-le tel quel)\n"
            . "- Les sections pros/cons sont fournies (intègre-les)\n"
            . "- Analyse détaillée de chaque entité (1-2 paragraphes chacune)\n"
            . "- Verdict final avec recommandation\n\n"
            . "Utilise des balises HTML: <h2>, <h3>, <p>, <strong>, <em>. Pas de <h1>.";

        $userPrompt = "Titre: {$title}\nEntités: " . implode(', ', $entities)
            . "\n\nTableau de comparaison à intégrer:\n{$tableHtml}"
            . "\n\nSections pros/cons à intégrer:\n{$prosConsHtml}"
            . "\n\nRédige l'article complet.";

        $result = $this->openAi->complete($systemPrompt, $userPrompt, [
            'temperature' => 0.7,
            'max_tokens' => 5000,
            'costable_type' => Comparative::class,
            'costable_id' => $comparative->id,
        ]);

        if ($result['success']) {
            return trim($result['content']);
        }

        // Fallback: return table + pros/cons
        return "<h2>Comparaison</h2>\n{$tableHtml}\n{$prosConsHtml}";
    }

    /**
     * Build HTML comparison table from structured data.
     */
    private function buildComparisonTableHtml(array $comparisonTable, array $entities): string
    {
        if (empty($comparisonTable)) {
            return '';
        }

        $html = "<table>\n<thead>\n<tr>\n<th>Critère</th>\n";
        foreach ($entities as $entity) {
            $html .= '<th>' . htmlspecialchars($entity) . "</th>\n";
        }
        $html .= "</tr>\n</thead>\n<tbody>\n";

        foreach ($comparisonTable as $row) {
            $criteria = $row['criteria'] ?? '';
            $values = $row['values'] ?? [];

            $html .= "<tr>\n<td><strong>" . htmlspecialchars($criteria) . "</strong></td>\n";
            foreach ($entities as $entity) {
                $value = $values[$entity] ?? '-';
                $html .= '<td>' . htmlspecialchars($value) . "</td>\n";
            }
            $html .= "</tr>\n";
        }

        $html .= "</tbody>\n</table>";

        return $html;
    }

    /**
     * Build HTML pros/cons section for an entity.
     */
    private function buildProsConsHtml(string $entity, array $data): string
    {
        $pros = $data['pros'] ?? [];
        $cons = $data['cons'] ?? [];
        $rating = $data['rating'] ?? 0;

        $html = '<h3>' . htmlspecialchars($entity) . " ({$rating}/5)</h3>\n";

        if (!empty($pros)) {
            $html .= "<p><strong>Avantages:</strong></p>\n<ul>\n";
            foreach ($pros as $pro) {
                $html .= '<li>' . htmlspecialchars($pro) . "</li>\n";
            }
            $html .= "</ul>\n";
        }

        if (!empty($cons)) {
            $html .= "<p><strong>Inconvénients:</strong></p>\n<ul>\n";
            foreach ($cons as $con) {
                $html .= '<li>' . htmlspecialchars($con) . "</li>\n";
            }
            $html .= "</ul>\n";
        }

        return $html;
    }

    /**
     * Generate meta tags for the comparative.
     */
    private function generateMeta(string $title, array $entities, string $language): array
    {
        $entitiesStr = implode(' vs ', $entities);

        $systemPrompt = "Génère des métadonnées SEO pour un comparatif. Langue: {$language}.\n"
            . "Retourne en JSON: {\"meta_title\": \"...\", \"meta_description\": \"...\", \"excerpt\": \"...\"}\n"
            . "meta_title: max 60 chars. meta_description: 140-160 chars. excerpt: 2-3 phrases.";

        $result = $this->openAi->complete($systemPrompt,
            "Titre: {$title}\nEntités: {$entitiesStr}", [
                'temperature' => 0.5,
                'max_tokens' => 400,
                'json_mode' => true,
            ]);

        if ($result['success']) {
            $parsed = json_decode($result['content'], true);
            return [
                'meta_title' => mb_substr($parsed['meta_title'] ?? $title, 0, 60),
                'meta_description' => mb_substr($parsed['meta_description'] ?? '', 0, 160),
                'excerpt' => $parsed['excerpt'] ?? '',
            ];
        }

        return [
            'meta_title' => mb_substr($title, 0, 60),
            'meta_description' => mb_substr("Comparaison détaillée: {$entitiesStr}", 0, 160),
            'excerpt' => "Découvrez notre comparatif détaillé: {$entitiesStr}.",
        ];
    }

    private function logPhase(Comparative $comparative, string $phase, string $status, ?string $message = null): void
    {
        try {
            GenerationLog::create([
                'loggable_type' => Comparative::class,
                'loggable_id' => $comparative->id,
                'phase' => $phase,
                'status' => $status,
                'message' => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to log comparative phase', ['phase' => $phase, 'message' => $e->getMessage()]);
        }
    }
}
