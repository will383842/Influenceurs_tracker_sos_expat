<?php

namespace App\Services\Content;

use App\Models\ResearchBrief;
use App\Models\TopicCluster;
use App\Services\AI\OpenAiService;
use App\Services\PerplexitySearchService;
use Illuminate\Support\Facades\Log;

/**
 * Research brief generation — multi-phase pipeline that extracts facts
 * from source articles, researches additional data, identifies gaps,
 * and suggests keywords + article structure.
 */
class ResearchBriefService
{
    public function __construct(
        private OpenAiService $openAi,
        private PerplexitySearchService $perplexity,
    ) {}

    /**
     * Generate a full research brief for a topic cluster.
     */
    public function generateBrief(TopicCluster $cluster): ResearchBrief
    {
        $startTime = microtime(true);

        Log::info('ResearchBrief: generation started', [
            'cluster_id' => $cluster->id,
            'cluster_name' => $cluster->name,
            'country' => $cluster->country,
        ]);

        try {
            $cluster->load('sourceArticles', 'clusterArticles');

            $topic = $cluster->name;
            $country = $cluster->country ?? '';
            $language = $cluster->language ?? 'fr';

            // ================================================================
            // Phase 1: Extract facts from each source article via GPT-4o-mini
            // ================================================================
            $extractedFacts = [];
            foreach ($cluster->sourceArticles as $article) {
                $contentText = strip_tags($article->content_html ?? $article->content_text ?? '');
                $contentText = mb_substr($contentText, 0, 3000);

                if (empty(trim($contentText))) {
                    continue;
                }

                $systemPrompt = "You are an expert analyst. Extract verifiable facts, statistics, "
                    . "procedures, and key information from the following article. "
                    . "Return a JSON object with these keys: key_facts (array of strings), "
                    . "statistics (array of strings), procedures (array of strings), "
                    . "sources (array of strings), outdated_info (array of strings), "
                    . "quality_rating (integer 1-10).";

                $result = $this->openAi->complete($systemPrompt, "Article: {$contentText}", [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.3,
                    'max_tokens' => 2000,
                    'json_mode' => true,
                ]);

                if ($result['success']) {
                    $parsed = json_decode($result['content'], true);
                    if (is_array($parsed)) {
                        $extractedFacts[] = $parsed;

                        // Update pivot with extracted facts
                        $cluster->clusterArticles()
                            ->where('source_article_id', $article->id)
                            ->update([
                                'extracted_facts' => $parsed,
                                'processing_status' => 'extracted',
                            ]);
                    }
                }
            }

            // ================================================================
            // Phase 2: Research via Perplexity for recent data + PAA
            // ================================================================
            $perplexityResponse = '';
            $recentData = [];
            $paaQuestions = [];

            if ($this->perplexity->isConfigured()) {
                $researchQuery = "Find recent and authoritative information about \"{$topic}\" "
                    . (!empty($country) ? "for {$country}" : '')
                    . ". Include: recent changes/updates, People Also Ask questions from Google, "
                    . "long-tail keywords people search for, official sources and statistics. "
                    . "Language: {$language}.";

                $researchResult = $this->perplexity->search($researchQuery, $language);

                if ($researchResult['success'] && !empty($researchResult['text'])) {
                    $perplexityResponse = $researchResult['text'];

                    // Parse recent data and PAA from the response via GPT
                    $parseResult = $this->openAi->complete(
                        "Parse the following research results and extract structured data. "
                        . "Return JSON with: recent_data (array of {fact, date, source}), "
                        . "paa_questions (array of strings), long_tail_keywords (array of strings).",
                        $perplexityResponse,
                        [
                            'model' => 'gpt-4o-mini',
                            'temperature' => 0.2,
                            'max_tokens' => 2000,
                            'json_mode' => true,
                        ]
                    );

                    if ($parseResult['success']) {
                        $parsedResearch = json_decode($parseResult['content'], true);
                        $recentData = $parsedResearch['recent_data'] ?? [];
                        $paaQuestions = $parsedResearch['paa_questions'] ?? [];
                    }
                }
            }

            // ================================================================
            // Phase 3: Identify gaps via GPT-4o-mini
            // ================================================================
            $identifiedGaps = [];

            $allFacts = [];
            foreach ($extractedFacts as $factSet) {
                $allFacts = array_merge($allFacts, $factSet['key_facts'] ?? []);
            }
            $factsContext = implode("\n", array_slice($allFacts, 0, 20));

            $gapResult = $this->openAi->complete(
                "You are an editorial analyst. Based on the facts extracted from existing articles "
                . "about \"{$topic}\"" . (!empty($country) ? " in {$country}" : '') . ", "
                . "identify what important aspects are NOT covered. "
                . "Return JSON: {gaps: [{topic, importance, description}]}",
                "Covered facts:\n{$factsContext}\n\nRecent research:\n" . mb_substr($perplexityResponse, 0, 2000),
                [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.4,
                    'max_tokens' => 1500,
                    'json_mode' => true,
                ]
            );

            if ($gapResult['success']) {
                $parsedGaps = json_decode($gapResult['content'], true);
                $identifiedGaps = $parsedGaps['gaps'] ?? [];
            }

            // ================================================================
            // Phase 4: Suggest keywords
            // ================================================================
            $suggestedKeywords = [];

            $keywordResult = $this->openAi->complete(
                "You are an SEO keyword researcher. Suggest keywords for an article about "
                . "\"{$topic}\"" . (!empty($country) ? " targeting {$country}" : '') . ". "
                . "Language: {$language}. "
                . "Return JSON: {primary: string, secondary: [strings], long_tail: [strings], lsi: [strings]}",
                "Topic: {$topic}\nExisting keywords from sources: "
                . json_encode($cluster->keywords_detected ?? []),
                [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.5,
                    'max_tokens' => 1000,
                    'json_mode' => true,
                ]
            );

            if ($keywordResult['success']) {
                $suggestedKeywords = json_decode($keywordResult['content'], true) ?: [];
            }

            // ================================================================
            // Phase 5: Suggest article structure (H2 outline)
            // ================================================================
            $suggestedStructure = [];

            $structureResult = $this->openAi->complete(
                "You are a content strategist. Suggest an article structure (H2 outline) for a "
                . "comprehensive article about \"{$topic}\""
                . (!empty($country) ? " for expatriates in {$country}" : '') . ". "
                . "Language: {$language}. "
                . "Return JSON: {sections: [{h2: string, description: string, subsections: [string]}]}",
                "Facts available:\n" . mb_substr($factsContext, 0, 1500)
                . "\n\nGaps to cover:\n" . json_encode(array_slice($identifiedGaps, 0, 5)),
                [
                    'model' => 'gpt-4o-mini',
                    'temperature' => 0.6,
                    'max_tokens' => 1500,
                    'json_mode' => true,
                ]
            );

            if ($structureResult['success']) {
                $parsedStructure = json_decode($structureResult['content'], true);
                $suggestedStructure = $parsedStructure['sections'] ?? $parsedStructure ?? [];
            }

            // ================================================================
            // Save research brief
            // ================================================================
            $brief = ResearchBrief::updateOrCreate(
                ['cluster_id' => $cluster->id],
                [
                    'perplexity_response' => mb_substr($perplexityResponse, 0, 10000),
                    'extracted_facts' => $extractedFacts,
                    'recent_data' => $recentData,
                    'identified_gaps' => $identifiedGaps,
                    'paa_questions' => $paaQuestions,
                    'suggested_keywords' => $suggestedKeywords,
                    'suggested_structure' => $suggestedStructure,
                    'tokens_used' => 0,
                    'cost_cents' => 0,
                ]
            );

            // Update cluster status
            $cluster->update(['status' => 'ready']);

            $durationMs = (int) ((microtime(true) - $startTime) * 1000);

            Log::info('ResearchBrief: generation complete', [
                'cluster_id' => $cluster->id,
                'brief_id' => $brief->id,
                'facts_extracted' => count($extractedFacts),
                'gaps_found' => count($identifiedGaps),
                'paa_questions' => count($paaQuestions),
                'duration_ms' => $durationMs,
            ]);

            return $brief;
        } catch (\Throwable $e) {
            Log::error('ResearchBrief: generation failed', [
                'cluster_id' => $cluster->id,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
