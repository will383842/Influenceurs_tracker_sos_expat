<?php

namespace App\Services\Quality;

use Illuminate\Support\Facades\Log;

/**
 * Readability analysis engine — 6 metrics adapted for French text.
 * Flesch, Flesch-Kincaid, Gunning Fog, SMOG, Coleman-Liau, ARI.
 */
class ReadabilityService
{
    /** French vowel pattern (including accented) for syllable counting. */
    private const VOWEL_PATTERN = '/[aeiouyàâèéêëîïôùûüÿ]+/iu';

    /** Common French abbreviations that end with a period (not sentence-ending). */
    private const ABBREVIATIONS = [
        'M.', 'Mme.', 'Mlle.', 'Dr.', 'Pr.', 'Me.', 'St.', 'Ste.',
        'Jr.', 'Sr.', 'Inc.', 'Ltd.', 'etc.', 'cf.', 'vol.', 'éd.',
        'ex.', 'env.', 'av.', 'apr.', 'janv.', 'févr.', 'avr.',
        'juil.', 'sept.', 'oct.', 'nov.', 'déc.',
    ];

    /** Suffixes excluded from "complex word" classification in Gunning Fog. */
    private const SIMPLE_SUFFIXES = ['-tion', '-ment', '-ement', '-ique'];

    /**
     * Run full readability analysis on a text.
     */
    public function analyze(string $text): array
    {
        try {
            $text = $this->stripHtml($text);

            if (empty(trim($text))) {
                return $this->emptyResult();
            }

            $sentences = $this->splitSentences($text);
            $sentenceCount = max(1, count($sentences));

            $words = $this->splitWords($text);
            $wordCount = max(1, count($words));

            $syllableCount = 0;
            $complexWordCount = 0;
            $charCount = 0;

            foreach ($words as $word) {
                $syl = $this->countSyllables($word);
                $syllableCount += $syl;
                $charCount += mb_strlen($word);

                if ($syl >= 3 && !$this->hasSimpleSuffix($word)) {
                    $complexWordCount++;
                }
            }

            $avgWordsPerSentence = $wordCount / $sentenceCount;
            $avgSyllablesPerWord = $syllableCount / $wordCount;

            // ================================================================
            // Formulas
            // ================================================================

            // Flesch Reading Ease (French — Kandel & Moles)
            $flesch = 207 - (1.015 * $avgWordsPerSentence) - (73.6 * $avgSyllablesPerWord);
            $flesch = round(max(0, min(100, $flesch)), 1);

            // Flesch-Kincaid Grade Level
            $fleschKincaid = (0.39 * $avgWordsPerSentence) + (11.8 * $avgSyllablesPerWord) - 15.59;
            $fleschKincaid = round(max(0, $fleschKincaid), 1);

            // Gunning Fog
            $complexPercent = ($wordCount > 0) ? ($complexWordCount / $wordCount) * 100 : 0;
            $gunningFog = 0.4 * ($avgWordsPerSentence + $complexPercent);
            $gunningFog = round(max(0, $gunningFog), 1);

            // SMOG (extrapolate if < 30 sentences)
            $smogFactor = ($sentenceCount >= 30)
                ? $complexWordCount
                : $complexWordCount * (30 / $sentenceCount);
            $smog = 3 + sqrt($smogFactor);
            $smog = round(max(0, $smog), 1);

            // Coleman-Liau
            $L = ($charCount / $wordCount) * 100; // avg chars per 100 words
            $S = ($sentenceCount / $wordCount) * 100; // avg sentences per 100 words
            $colemanLiau = (0.0588 * $L) - (0.296 * $S) - 15.8;
            $colemanLiau = round(max(0, $colemanLiau), 1);

            // ARI
            $ari = (4.71 * ($charCount / $wordCount)) + (0.5 * $avgWordsPerSentence) - 21.43;
            $ari = round(max(0, $ari), 1);

            // ================================================================
            // Overall score & classification
            // ================================================================
            $overallScore = round(
                ($flesch * 0.35)
                + ((100 - min(100, $fleschKincaid * 5)) * 0.15)
                + ((100 - min(100, $gunningFog * 5)) * 0.15)
                + ((100 - min(100, $smog * 5)) * 0.10)
                + ((100 - min(100, $colemanLiau * 5)) * 0.10)
                + ((100 - min(100, $ari * 5)) * 0.15),
                1
            );
            $overallScore = max(0, min(100, $overallScore));

            $readingLevel = $this->classifyLevel($overallScore);
            $recommendations = $this->generateRecommendations(
                $avgWordsPerSentence,
                $avgSyllablesPerWord,
                $flesch,
                $gunningFog,
            );

            return [
                'flesch_reading_ease'     => $flesch,
                'flesch_kincaid_grade'    => $fleschKincaid,
                'gunning_fog'             => $gunningFog,
                'smog_index'              => $smog,
                'coleman_liau'            => $colemanLiau,
                'ari'                     => $ari,
                'avg_words_per_sentence'  => round($avgWordsPerSentence, 1),
                'avg_syllables_per_word'  => round($avgSyllablesPerWord, 2),
                'sentence_count'          => $sentenceCount,
                'word_count'              => $wordCount,
                'syllable_count'          => $syllableCount,
                'reading_level'           => $readingLevel,
                'overall_score'           => $overallScore,
                'recommendations'         => $recommendations,
            ];
        } catch (\Throwable $e) {
            Log::error('Readability analysis failed', ['message' => $e->getMessage()]);

            throw $e;
        }
    }

    // ============================================================
    // Private helpers
    // ============================================================

    /**
     * Strip HTML tags, scripts, styles, and decode entities.
     */
    private function stripHtml(string $text): string
    {
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
        $text = preg_replace('/<\/(p|div|h[1-6]|li|tr|td|th|br|hr)[^>]*>/i', ' ', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', trim($text));

        return $text;
    }

    /**
     * Split text into sentences, handling French abbreviations.
     */
    private function splitSentences(string $text): array
    {
        // Protect abbreviations by replacing their period temporarily
        $protected = $text;
        foreach (self::ABBREVIATIONS as $abbr) {
            $safe = str_replace('.', '∙', $abbr);
            $protected = str_replace($abbr, $safe, $protected);
        }

        // Also protect numbers with dots (e.g. 3.5, 1.000)
        $protected = preg_replace('/(\d)\.(\d)/', '$1∙$2', $protected);

        // Split on sentence-ending punctuation
        $sentences = preg_split('/[.!?]+/', $protected, -1, PREG_SPLIT_NO_EMPTY);

        // Filter out empty sentences
        return array_values(array_filter(
            $sentences,
            fn (string $s) => mb_strlen(trim($s)) > 2
        ));
    }

    /**
     * Split text into words.
     */
    private function splitWords(string $text): array
    {
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        // Filter out pure punctuation tokens
        return array_values(array_filter(
            $words,
            fn (string $w) => preg_match('/\pL/u', $w)
        ));
    }

    /**
     * Count syllables in a French word.
     * Rules: count vowel groups, subtract silent-e at end, minimum 1.
     */
    private function countSyllables(string $word): int
    {
        $word = mb_strtolower(trim($word));

        // Remove non-letter characters
        $word = preg_replace('/[^\pL]/u', '', $word);

        if (mb_strlen($word) === 0) {
            return 1;
        }

        // Count vowel groups
        $count = preg_match_all(self::VOWEL_PATTERN, $word);

        // Subtract silent-e at end of French words (if not the only vowel)
        if ($count > 1 && preg_match('/e$/u', $word)) {
            // Check the second-to-last char isn't also a vowel (would be a vowel group already counted)
            $beforeE = mb_substr($word, 0, -1);
            if (!preg_match('/[aeiouyàâèéêëîïôùûüÿ]$/iu', $beforeE)) {
                $count--;
            }
        }

        return max(1, $count);
    }

    /**
     * Check if a word ends with a "simple" suffix (excluded from complex word count).
     */
    private function hasSimpleSuffix(string $word): bool
    {
        $word = mb_strtolower($word);

        foreach (self::SIMPLE_SUFFIXES as $suffix) {
            $cleanSuffix = ltrim($suffix, '-');
            if (mb_strlen($word) > mb_strlen($cleanSuffix) && str_ends_with($word, $cleanSuffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Classify reading level from overall score.
     */
    private function classifyLevel(float $score): string
    {
        if ($score >= 80) {
            return 'elementary';
        }
        if ($score >= 65) {
            return 'middle_school';
        }
        if ($score >= 50) {
            return 'high_school';
        }
        if ($score >= 30) {
            return 'college';
        }

        return 'graduate';
    }

    /**
     * Generate actionable recommendations in French.
     */
    private function generateRecommendations(
        float $avgWordsPerSentence,
        float $avgSyllablesPerWord,
        float $flesch,
        float $gunningFog,
    ): array {
        $recommendations = [];

        if ($avgWordsPerSentence > 20) {
            $avg = round($avgWordsPerSentence, 1);
            $recommendations[] = "Raccourcir les phrases (moyenne: {$avg} mots, cible: 15-18)";
        }

        if ($avgSyllablesPerWord > 2.0) {
            $avg = round($avgSyllablesPerWord, 2);
            $recommendations[] = "Simplifier le vocabulaire (moyenne: {$avg} syllabes/mot)";
        }

        if ($flesch < 50) {
            $recommendations[] = 'Texte trop complexe pour un public non-natif';
        }

        if ($gunningFog > 14) {
            $fog = round($gunningFog, 1);
            $recommendations[] = "Niveau de lecture trop élevé (Fog: {$fog}, cible: <12)";
        }

        return $recommendations;
    }

    /**
     * Return an empty/default result for blank text.
     */
    private function emptyResult(): array
    {
        return [
            'flesch_reading_ease'     => 0,
            'flesch_kincaid_grade'    => 0,
            'gunning_fog'             => 0,
            'smog_index'              => 0,
            'coleman_liau'            => 0,
            'ari'                     => 0,
            'avg_words_per_sentence'  => 0,
            'avg_syllables_per_word'  => 0,
            'sentence_count'          => 0,
            'word_count'              => 0,
            'syllable_count'          => 0,
            'reading_level'           => 'elementary',
            'overall_score'           => 0,
            'recommendations'         => ['Aucun texte à analyser.'],
        ];
    }
}
