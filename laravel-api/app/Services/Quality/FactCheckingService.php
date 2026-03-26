<?php

namespace App\Services\Quality;

use App\Services\PerplexitySearchService;
use Illuminate\Support\Facades\Log;

/**
 * Fact-checking engine — extracts claims from content and verifies them
 * against real-time web sources via Perplexity API.
 */
class FactCheckingService
{
    /** Maximum claims to verify per check (controls API cost). */
    private const MAX_CLAIMS_TO_VERIFY = 10;

    private PerplexitySearchService $perplexity;

    public function __construct(PerplexitySearchService $perplexity)
    {
        $this->perplexity = $perplexity;
    }

    /**
     * Extract claims from content and verify them.
     */
    public function check(string $contentHtml, string $country, string $language = 'fr'): array
    {
        try {
            $text = $this->extractText($contentHtml);

            if (empty(trim($text))) {
                return $this->emptyResult();
            }

            // 1. Extract claims
            $rawClaims = $this->extractClaims($text);

            // 2. Deduplicate similar claims
            $claims = $this->deduplicateClaims($rawClaims);

            // 3. Limit to max claims
            $claimsToVerify = array_slice($claims, 0, self::MAX_CLAIMS_TO_VERIFY);

            // 4. Verify each claim
            $verifiedClaims = [];
            $verified = 0;
            $disputed = 0;
            $unverified = 0;

            foreach ($claimsToVerify as $claim) {
                $result = $this->verifyClaim($claim['text'], $country, $language);

                $verifiedClaim = [
                    'text'       => $claim['text'],
                    'type'       => $claim['type'],
                    'status'     => $result['status'],
                    'confidence' => $result['confidence'],
                    'sources'    => $result['sources'],
                    'suggestion' => $result['suggestion'],
                ];

                $verifiedClaims[] = $verifiedClaim;

                match ($result['status']) {
                    'verified'   => $verified++,
                    'disputed'   => $disputed++,
                    'unverified' => $unverified++,
                };
            }

            Log::info('Fact-checking complete', [
                'country'    => $country,
                'claims'     => count($claims),
                'verified'   => $verified,
                'disputed'   => $disputed,
                'unverified' => $unverified,
            ]);

            return [
                'claims_found' => count($claims),
                'verified'     => $verified,
                'disputed'     => $disputed,
                'unverified'   => $unverified,
                'claims'       => $verifiedClaims,
            ];
        } catch (\Throwable $e) {
            Log::error('Fact-checking failed', [
                'country' => $country,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    // ============================================================
    // Claim extraction
    // ============================================================

    /**
     * Extract verifiable claims from text using regex patterns.
     *
     * @return array<array{text: string, type: string, context_sentence: string}>
     */
    private function extractClaims(string $text): array
    {
        $claims = [];

        // Statistics: numbers with units (%, millions, euros, etc.)
        $patterns = [
            'statistic' => '/(\d[\d\s,.]*\s*(%|pour\s*cent|millions?|milliards?|euros?|€|\$|dollars?))/ui',
            'cost'      => '/(coûte?|tarif|prix|frais|montant)\s*:?\s*\d[\d\s,.]*\s*(€|euros?|\$|dollars?)/ui',
            'date'      => '/(depuis|en|à partir de|jusqu\'en|avant|après)\s*(19|20)\d{2}/ui',
            'regulation' => '/(loi|décret|directive|règlement|article\s+\d|code)\s+[^.]{5,50}/ui',
            'quantity'  => '/(\d[\d\s,.]+)\s*(jours?|semaines?|mois|ans?|heures?|personnes?|habitants?)/ui',
        ];

        foreach ($patterns as $type => $pattern) {
            if (preg_match_all($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[0] as [$match, $offset]) {
                    $contextSentence = $this->extractSentenceAt($text, $offset);

                    // Skip very short matches (likely noise)
                    if (mb_strlen(trim($match)) < 3) {
                        continue;
                    }

                    $claims[] = [
                        'text'             => trim($contextSentence),
                        'type'             => $type,
                        'context_sentence' => $contextSentence,
                    ];
                }
            }
        }

        return $claims;
    }

    /**
     * Deduplicate claims that are substantially similar.
     */
    private function deduplicateClaims(array $claims): array
    {
        $unique = [];
        $seen = [];

        foreach ($claims as $claim) {
            // Use first 80 chars as dedup key
            $key = mb_strtolower(mb_substr($claim['text'], 0, 80));

            $isDuplicate = false;
            foreach ($seen as $seenKey) {
                if (similar_text($key, $seenKey, $percent) && $percent > 70) {
                    $isDuplicate = true;
                    break;
                }
                // Also use similar_text return value for very short strings
                if ($key === $seenKey) {
                    $isDuplicate = true;
                    break;
                }
            }

            if (!$isDuplicate) {
                $unique[] = $claim;
                $seen[] = $key;
            }
        }

        return $unique;
    }

    // ============================================================
    // Claim verification
    // ============================================================

    /**
     * Verify a single claim via Perplexity web search.
     *
     * @return array{status: string, confidence: string, sources: string[], suggestion: string|null}
     */
    private function verifyClaim(string $claim, string $country, string $language): array
    {
        $currentYear = date('Y');
        $query = "Vérifier: {$claim} {$country} source officielle {$currentYear}";

        $searchResult = $this->perplexity->search($query, $language);

        if (!($searchResult['success'] ?? false)) {
            return [
                'status'     => 'unverified',
                'confidence' => 'low',
                'sources'    => [],
                'suggestion' => null,
            ];
        }

        $responseText = $searchResult['text'] ?? '';
        $citations = $searchResult['citations'] ?? [];

        // Parse the response for verification signals
        return $this->parseVerificationResponse($responseText, $citations, $claim);
    }

    /**
     * Parse Perplexity response to determine claim status.
     */
    private function parseVerificationResponse(string $responseText, array $citations, string $originalClaim): array
    {
        $responseLower = mb_strtolower($responseText);

        // Signals for contradiction
        $contradictionSignals = [
            'incorrect', 'faux', 'erroné', 'inexact', 'obsolète',
            'n\'est plus', 'a changé', 'a été modifié', 'n\'est pas exact',
            'en réalité', 'en fait', 'contrairement', 'cependant',
            'attention', 'mise à jour', 'ancien', 'anciennement',
        ];

        // Signals for confirmation
        $confirmationSignals = [
            'confirme', 'exact', 'correct', 'effectivement', 'en effet',
            'c\'est bien', 'selon les sources', 'les sources confirment',
            'officiellement', 'd\'après',
        ];

        $contradictionCount = 0;
        foreach ($contradictionSignals as $signal) {
            if (mb_strpos($responseLower, $signal) !== false) {
                $contradictionCount++;
            }
        }

        $confirmationCount = 0;
        foreach ($confirmationSignals as $signal) {
            if (mb_strpos($responseLower, $signal) !== false) {
                $confirmationCount++;
            }
        }

        $sourceCount = count($citations);

        // Determine confidence
        if ($sourceCount >= 2 && $confirmationCount >= 1) {
            $confidence = 'high';
        } elseif ($sourceCount >= 1 && $contradictionCount === 0) {
            $confidence = 'medium';
        } else {
            $confidence = 'low';
        }

        // Determine status
        if ($contradictionCount >= 1) {
            $status = 'disputed';
            $suggestion = $this->extractSuggestion($responseText);
        } elseif ($confidence === 'high' && $contradictionCount === 0) {
            $status = 'verified';
            $suggestion = null;
        } else {
            $status = 'unverified';
            $suggestion = null;
        }

        return [
            'status'     => $status,
            'confidence' => $confidence,
            'sources'    => array_slice($citations, 0, 5),
            'suggestion' => $suggestion,
        ];
    }

    /**
     * Extract a correction suggestion from the Perplexity response.
     */
    private function extractSuggestion(string $responseText): ?string
    {
        // Try to find sentences with correction keywords
        $sentences = preg_split('/[.!?]+/', $responseText, -1, PREG_SPLIT_NO_EMPTY);

        $correctionKeywords = ['en réalité', 'le chiffre correct', 'actuellement', 'en fait', 'la valeur actuelle', 'le montant actuel'];

        foreach ($sentences as $sentence) {
            $lower = mb_strtolower($sentence);
            foreach ($correctionKeywords as $keyword) {
                if (mb_strpos($lower, $keyword) !== false) {
                    return trim($sentence);
                }
            }
        }

        // Fallback: return first sentence of the response as context
        if (!empty($sentences)) {
            return trim($sentences[0]);
        }

        return null;
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Extract the sentence surrounding a character offset.
     */
    private function extractSentenceAt(string $text, int $offset): string
    {
        $start = max(0, $offset - 200);
        $before = mb_substr($text, $start, $offset - $start);
        $lastBreak = max(
            (int) mb_strrpos($before, '.'),
            (int) mb_strrpos($before, '!'),
            (int) mb_strrpos($before, '?'),
        );
        $sentenceStart = ($lastBreak > 0) ? $start + $lastBreak + 1 : $start;

        $after = mb_substr($text, $offset, 300);
        $nextBreak = false;
        foreach (['.', '!', '?'] as $punct) {
            $pos = mb_strpos($after, $punct);
            if ($pos !== false && ($nextBreak === false || $pos < $nextBreak)) {
                $nextBreak = $pos;
            }
        }
        $sentenceEnd = ($nextBreak !== false) ? $offset + $nextBreak + 1 : min(mb_strlen($text), $offset + 300);

        return trim(mb_substr($text, $sentenceStart, $sentenceEnd - $sentenceStart));
    }

    /**
     * Extract plain text from HTML.
     */
    private function extractText(string $html): string
    {
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        $html = preg_replace('/<\/(p|div|h[1-6]|li|tr|td|th|br|hr)[^>]*>/i', ' ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\s+/', ' ', trim($text));
    }

    /**
     * Empty result for blank content.
     */
    private function emptyResult(): array
    {
        return [
            'claims_found' => 0,
            'verified'     => 0,
            'disputed'     => 0,
            'unverified'   => 0,
            'claims'       => [],
        ];
    }
}
