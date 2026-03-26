<?php

namespace App\Services\Quality;

use Illuminate\Support\Facades\Log;

/**
 * Brand compliance checker — 6 checks for SOS-Expat content guidelines.
 * Tutoiement, superlatives, emojis, exclamation abuse, CAPS abuse, sentence length.
 */
class BrandComplianceService
{
    /** Known acronyms to exclude from CAPS abuse detection. */
    private const KNOWN_ACRONYMS = [
        'UE', 'USA', 'UK', 'OFII', 'VFS', 'TLS', 'ANTS', 'CPAM', 'RSI',
        'TVA', 'SCI', 'SARL', 'SAS', 'EURL', 'PDF', 'FAQ', 'SEO', 'API',
        'CTA', 'IVR', 'SMS', 'QR', 'HTML', 'CSS', 'PHP', 'SQL', 'RGPD',
        'DELF', 'DALF', 'TCF', 'VISA', 'CNIL', 'INSEE', 'URSSAF', 'CAF',
    ];

    /** Whitelisted informational emojis. */
    private const EMOJI_WHITELIST = ['✅', '❌', '⚠️', 'ℹ️', '📌', '📋', '💡'];

    /** False positive words for tutoiement detection. */
    private const TUTOIEMENT_EXCEPTIONS = [
        'tuteur', 'tutorat', 'tutelle', 'turc', 'turque', 'tunnel',
        'turbine', 'tulipe', 'tumeur', 'tunisie', 'tunisien', 'turquie',
        'tutrice', 'tuba', 'tube', 'tuile', 'tuméfié',
    ];

    /** French superlatives and hyperbolic markers. */
    private const SUPERLATIVES = [
        'le meilleur', 'la meilleure', 'les meilleurs', 'les meilleures',
        'le pire', 'la pire', 'le plus', 'la plus', 'les plus',
        'incroyable', 'extraordinaire', 'exceptionnel', 'exceptionnelle',
        'phénoménal', 'révolutionnaire', 'ultime', 'parfait', 'parfaite',
        'absolu', 'absolue', 'incomparable', 'imbattable',
        'numéro 1', 'n°1', 'leader', 'hyper', 'ultra', 'méga',
    ];

    /**
     * Run all brand compliance checks on HTML content.
     */
    public function check(string $html): array
    {
        try {
            $text = $this->extractText($html);

            if (empty(trim($text))) {
                return $this->emptyResult();
            }

            $tutoiement       = $this->checkTutoiement($text);
            $superlatives     = $this->checkSuperlatives($text);
            $emojis           = $this->checkEmojis($text);
            $exclamationAbuse = $this->checkExclamationAbuse($text);
            $capsAbuse        = $this->checkCapsAbuse($text, $html);
            $sentenceLength   = $this->checkSentenceLength($text);

            $checks = [
                'tutoiement'       => $tutoiement,
                'superlatives'     => $superlatives,
                'emojis'           => $emojis,
                'exclamation_abuse' => $exclamationAbuse,
                'caps_abuse'       => $capsAbuse,
                'sentence_length'  => $sentenceLength,
            ];

            // Build violations list
            $violations = [];

            if (!$tutoiement['passed']) {
                foreach ($tutoiement['instances'] as $instance) {
                    $violations[] = [
                        'type'       => 'tutoiement',
                        'severity'   => 'error',
                        'message'    => 'Utilisation du tutoiement détectée',
                        'context'    => $instance,
                        'suggestion' => 'Remplacer par le vouvoiement (vous/votre)',
                    ];
                }
            }

            if (!$superlatives['passed']) {
                foreach ($superlatives['instances'] as $instance) {
                    $violations[] = [
                        'type'       => 'superlatives',
                        'severity'   => 'warning',
                        'message'    => 'Superlatif/hyperbole détecté',
                        'context'    => $instance,
                        'suggestion' => 'Remplacer par une formulation factuelle et mesurée',
                    ];
                }
            }

            if (!$emojis['passed']) {
                foreach ($emojis['instances'] as $instance) {
                    $violations[] = [
                        'type'       => 'emojis',
                        'severity'   => 'warning',
                        'message'    => 'Emoji non-informationnel détecté',
                        'context'    => $instance,
                        'suggestion' => 'Supprimer ou remplacer par un emoji informatif (✅ ❌ ⚠️ ℹ️ 📌 📋 💡)',
                    ];
                }
            }

            if (!$exclamationAbuse['passed']) {
                foreach ($exclamationAbuse['instances'] as $instance) {
                    $violations[] = [
                        'type'       => 'exclamation_abuse',
                        'severity'   => 'warning',
                        'message'    => 'Abus de points d\'exclamation',
                        'context'    => $instance,
                        'suggestion' => 'Limiter à un seul point d\'exclamation par phrase, max 3 par article',
                    ];
                }
            }

            if (!$capsAbuse['passed']) {
                foreach ($capsAbuse['instances'] as $instance) {
                    $violations[] = [
                        'type'       => 'caps_abuse',
                        'severity'   => 'error',
                        'message'    => 'Mot en majuscules non-acronyme détecté',
                        'context'    => $instance,
                        'suggestion' => 'Mettre en minuscules ou en gras pour l\'emphase',
                    ];
                }
            }

            if (!$sentenceLength['passed']) {
                $violations[] = [
                    'type'       => 'sentence_length',
                    'severity'   => 'warning',
                    'message'    => "Phrases trop longues (max: {$sentenceLength['max_length']} mots, moy: " . round($sentenceLength['avg_length'], 1) . ')',
                    'context'    => implode(' | ', array_slice($sentenceLength['long_sentences'] ?? [], 0, 3)),
                    'suggestion' => 'Raccourcir les phrases à 40 mots maximum, viser une moyenne de 22 mots',
                ];
            }

            // Score: percentage of checks passed
            $passedCount = count(array_filter($checks, fn (array $c) => $c['passed']));
            $totalChecks = count($checks);
            $score = (int) round(($passedCount / $totalChecks) * 100);

            $isCompliant = empty($violations) || !collect($violations)->contains('severity', 'error');

            return [
                'is_compliant' => $isCompliant,
                'score'        => $score,
                'violations'   => $violations,
                'checks'       => $checks,
            ];
        } catch (\Throwable $e) {
            Log::error('Brand compliance check failed', ['message' => $e->getMessage()]);

            throw $e;
        }
    }

    // ============================================================
    // Individual checks
    // ============================================================

    /**
     * Detect tutoiement (tu/ton/ta/tes/toi), excluding false positives.
     */
    private function checkTutoiement(string $text): array
    {
        $instances = [];

        // Match "tu ", "t'", "ton ", "ta ", "tes ", "toi "
        if (preg_match_all('/\b(tu\s|t\'|ton\s|ta\s|tes\s|toi\s)/ui', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$match, $offset]) {
                // Extract surrounding context (the sentence)
                $context = $this->extractSentenceAt($text, $offset);

                // Check for false positives
                if ($this->isTutoiementFalsePositive($context)) {
                    continue;
                }

                $instances[] = trim($context);
            }
        }

        // Deduplicate
        $instances = array_values(array_unique($instances));

        return [
            'passed'    => empty($instances),
            'instances' => $instances,
        ];
    }

    /**
     * Detect superlatives and hyperbolic language.
     * Max allowed: 2 per 1000 words.
     */
    private function checkSuperlatives(string $text): array
    {
        $instances = [];
        $textLower = mb_strtolower($text);

        foreach (self::SUPERLATIVES as $superlative) {
            $pos = 0;
            while (($pos = mb_strpos($textLower, $superlative, $pos)) !== false) {
                $context = $this->extractSentenceAt($text, $pos);
                $instances[] = trim($context);
                $pos += mb_strlen($superlative);
            }
        }

        // Check "super" as a prefix (e.g. "super facile") but not standalone word in a different context
        if (preg_match_all('/\bsuper\s+\w+/ui', $text, $superMatches)) {
            foreach ($superMatches[0] as $match) {
                $instances[] = $match;
            }
        }

        $instances = array_values(array_unique($instances));

        // Max 2 per 1000 words
        $wordCount = max(1, count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY)));
        $maxAllowed = max(2, (int) ceil(($wordCount / 1000) * 2));

        return [
            'passed'    => count($instances) <= $maxAllowed,
            'instances' => $instances,
        ];
    }

    /**
     * Detect non-whitelisted emojis.
     */
    private function checkEmojis(string $text): array
    {
        $instances = [];

        $emojiPattern = '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u';

        if (preg_match_all($emojiPattern, $text, $matches)) {
            foreach ($matches[0] as $emoji) {
                if (!in_array($emoji, self::EMOJI_WHITELIST)) {
                    $instances[] = $emoji;
                }
            }
        }

        $instances = array_values(array_unique($instances));

        return [
            'passed'    => empty($instances),
            'instances' => $instances,
        ];
    }

    /**
     * Detect exclamation mark abuse (!! or !!!, or >3 per 1000 words).
     */
    private function checkExclamationAbuse(string $text): array
    {
        $instances = [];

        // Detect multiple exclamation marks (!!, !!!)
        if (preg_match_all('/!{2,}/', $text, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$match, $offset]) {
                $context = $this->extractSentenceAt($text, $offset);
                $instances[] = trim($context);
            }
        }

        // Count total single exclamation marks
        $totalExclamations = mb_substr_count($text, '!');
        $wordCount = max(1, count(preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY)));
        $maxAllowed = max(3, (int) ceil(($wordCount / 1000) * 3));

        // Flag sentences with more than 1 exclamation
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($sentences as $sentence) {
            $count = mb_substr_count($sentence, '!');
            if ($count > 1 && !in_array(trim($sentence), $instances)) {
                $instances[] = trim($sentence);
            }
        }

        $instances = array_values(array_unique($instances));

        $passed = empty($instances) && $totalExclamations <= $maxAllowed;

        return [
            'passed'    => $passed,
            'instances' => $instances,
        ];
    }

    /**
     * Detect ALL CAPS words (4+ chars) that are not known acronyms.
     * Exclude content inside heading tags.
     */
    private function checkCapsAbuse(string $text, string $html): array
    {
        $instances = [];

        // Extract heading content to exclude
        $headingTexts = '';
        if (preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', $html, $headingMatches)) {
            $headingTexts = mb_strtoupper(implode(' ', array_map('strip_tags', $headingMatches[1])));
        }

        // Find ALL CAPS words (4+ characters)
        if (preg_match_all('/\b([A-ZÀ-Ü]{4,})\b/u', $text, $matches)) {
            foreach ($matches[1] as $capsWord) {
                // Skip known acronyms
                if (in_array($capsWord, self::KNOWN_ACRONYMS)) {
                    continue;
                }

                // Skip if it's inside a heading
                if (!empty($headingTexts) && mb_strpos($headingTexts, $capsWord) !== false) {
                    continue;
                }

                $instances[] = $capsWord;
            }
        }

        $instances = array_values(array_unique($instances));

        return [
            'passed'    => empty($instances),
            'instances' => $instances,
        ];
    }

    /**
     * Check sentence length: flag > 40 words, average should be < 22.
     */
    private function checkSentenceLength(string $text): array
    {
        $sentences = preg_split('/[.!?]+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        $sentences = array_filter($sentences, fn (string $s) => mb_strlen(trim($s)) > 2);

        $longSentences = [];
        $totalWords = 0;
        $maxLength = 0;

        foreach ($sentences as $sentence) {
            $words = preg_split('/\s+/', trim($sentence), -1, PREG_SPLIT_NO_EMPTY);
            $wordCount = count($words);
            $totalWords += $wordCount;

            if ($wordCount > $maxLength) {
                $maxLength = $wordCount;
            }

            if ($wordCount > 40) {
                $longSentences[] = mb_substr(trim($sentence), 0, 120) . '...';
            }
        }

        $sentenceCount = max(1, count($sentences));
        $avgLength = $totalWords / $sentenceCount;

        $passed = empty($longSentences) && $avgLength < 22;

        return [
            'passed'          => $passed,
            'max_length'      => $maxLength,
            'avg_length'      => round($avgLength, 1),
            'long_sentences'  => $longSentences,
        ];
    }

    // ============================================================
    // Helpers
    // ============================================================

    /**
     * Extract the sentence surrounding a character offset.
     */
    private function extractSentenceAt(string $text, int $offset): string
    {
        // Find start of sentence (previous .!? or start of text)
        $start = max(0, $offset - 200);
        $before = mb_substr($text, $start, $offset - $start);
        $lastBreak = max(
            (int) mb_strrpos($before, '.'),
            (int) mb_strrpos($before, '!'),
            (int) mb_strrpos($before, '?'),
        );
        $sentenceStart = ($lastBreak > 0) ? $start + $lastBreak + 1 : $start;

        // Find end of sentence
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
     * Check if a tutoiement match is a false positive.
     */
    private function isTutoiementFalsePositive(string $context): bool
    {
        $lower = mb_strtolower($context);

        foreach (self::TUTOIEMENT_EXCEPTIONS as $exception) {
            if (mb_strpos($lower, $exception) !== false) {
                return true;
            }
        }

        return false;
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
            'is_compliant' => true,
            'score'        => 100,
            'violations'   => [],
            'checks'       => [
                'tutoiement'        => ['passed' => true, 'instances' => []],
                'superlatives'      => ['passed' => true, 'instances' => []],
                'emojis'            => ['passed' => true, 'instances' => []],
                'exclamation_abuse' => ['passed' => true, 'instances' => []],
                'caps_abuse'        => ['passed' => true, 'instances' => []],
                'sentence_length'   => ['passed' => true, 'max_length' => 0, 'avg_length' => 0.0],
            ],
        ];
    }
}
