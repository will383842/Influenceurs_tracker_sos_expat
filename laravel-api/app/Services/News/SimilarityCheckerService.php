<?php

namespace App\Services\News;

class SimilarityCheckerService
{
    /**
     * Compute Jaccard similarity (0-100) between two texts using word trigrams.
     */
    public function compute(string $original, string $generated): int
    {
        $normA = $this->normalize($original);
        $normB = $this->normalize($generated);

        $trigramsA = $this->trigrams($normA);
        $trigramsB = $this->trigrams($normB);

        if (empty($trigramsA) || empty($trigramsB)) {
            return 0;
        }

        $intersection = count(array_intersect_key(
            array_flip($trigramsA),
            array_flip($trigramsB)
        ));

        $union = count(array_unique(array_merge($trigramsA, $trigramsB)));

        if ($union === 0) {
            return 0;
        }

        return (int) round(($intersection / $union) * 100);
    }

    // ─────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────

    private function normalize(string $text): array
    {
        // Supprimer HTML
        $text = strip_tags($text);

        // Lowercase
        $text = mb_strtolower($text);

        // Supprimer ponctuation
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);

        // Tokeniser
        $words = preg_split('/\s+/', trim($text), -1, PREG_SPLIT_NO_EMPTY);

        // Supprimer stopwords
        $stopwords = [
            'le', 'la', 'les', 'de', 'du', 'un', 'une', 'des', 'et', 'ou',
            'à', 'en', 'pour', 'par', 'sur', 'dans', 'avec', 'que', 'qui',
            'est', 'sont', 'ce', 'se', 'sa', 'son', 'ses', 'au', 'aux',
            'il', 'elle', 'ils', 'elles', 'nous', 'vous', 'je', 'tu',
            'me', 'te', 'lui', 'y', 'on', 'ne', 'pas', 'plus', 'aussi',
            'mais', 'car', 'donc', 'or', 'ni', 'si', 'the', 'a', 'an',
            'of', 'to', 'in', 'is', 'it', 'and', 'or', 'for', 'on',
            'at', 'by', 'be', 'as', 'are', 'was', 'this', 'that', 'with',
        ];

        return array_values(array_filter($words, fn($w) => ! in_array($w, $stopwords, true) && mb_strlen($w) > 1));
    }

    private function trigrams(array $words): array
    {
        $trigrams = [];
        $count    = count($words);

        for ($i = 0; $i <= $count - 3; $i++) {
            $trigrams[] = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
        }

        return $trigrams;
    }
}
