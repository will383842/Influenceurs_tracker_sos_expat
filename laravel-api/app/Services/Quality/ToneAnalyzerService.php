<?php

namespace App\Services\Quality;

use Illuminate\Support\Facades\Log;

/**
 * Tone analyzer — 5 dimensions (sentiment, formality, emotion, urgency, engagement).
 * Tuned for SOS-Expat brand compliance: professional_optimistic tone.
 */
class ToneAnalyzerService
{
    /** Positive French words for sentiment scoring. */
    private const POSITIVE_WORDS = [
        'excellent', 'parfait', 'idéal', 'avantage', 'opportunité', 'simple', 'facile',
        'gratuit', 'sécurisé', 'bienvenue', 'réussir', 'profiter', 'bénéficier',
        'accompagner', 'aider', 'solution', 'succès', 'qualité', 'fiable', 'efficace',
        'rapide', 'pratique', 'accessible', 'confort', 'sérénité', 'confiance',
        'progrès', 'améliorer', 'optimiser', 'recommandé', 'apprécié', 'satisfait',
        'soutien', 'protection', 'garantie', 'performant', 'innovant', 'moderne',
        'flexible', 'compétitif', 'abordable', 'transparent', 'personnalisé',
    ];

    /** Negative French words for sentiment scoring. */
    private const NEGATIVE_WORDS = [
        'problème', 'difficulté', 'compliqué', 'risque', 'danger', 'attention',
        'malheureusement', 'refus', 'rejet', 'échec', 'pénalité', 'amende',
        'interdiction', 'impossible', 'coûteux', 'perte', 'obstacle', 'contrainte',
        'erreur', 'retard', 'ennui', 'inquiétude', 'incertitude', 'méfiance',
        'arnaque', 'fraude', 'illégal', 'sanction', 'expulsion', 'menace',
        'insécurité', 'précarité', 'galère', 'cauchemar', 'piège',
    ];

    /** Formal indicators (French). */
    private const FORMAL_INDICATORS = [
        'vous', 'votre', 'vos', 'veuillez', 'il convient', 'nous vous recommandons',
        'il est conseillé', 'conformément', 'en vertu de', 'dans le cadre de',
        'il est important', 'nous vous invitons', 'à cet effet', 'par conséquent',
        'en outre', 'toutefois', 'néanmoins', 'cependant', 'ainsi',
    ];

    /** Informal indicators (French). */
    private const INFORMAL_INDICATORS = [
        ' tu ', " t'", ' ton ', ' ta ', ' tes ', ' toi ',
        'super', 'cool', ' ok ', 'ouais', 'genre', 'en gros',
        'du coup', 'bref', 'carrément', 'grave', 'trop bien',
        'kiffer', 'relou', 'ouf', 'dingue', 'mdr', 'lol',
    ];

    /** Emotional intensifiers (French). */
    private const EMOTIONAL_WORDS = [
        'incroyable', 'extraordinaire', 'fantastique', 'terrible', 'urgent',
        'choquant', 'magnifique', 'formidable', 'épouvantable', 'sensationnel',
        'hallucinant', 'stupéfiant', 'phénoménal', 'exceptionnel', 'merveilleux',
        'désastreux', 'catastrophique', 'révoltant', 'scandaleux',
    ];

    /** Urgency phrases (French). */
    private const URGENCY_PHRASES = [
        'dépêchez-vous', 'immédiatement', 'sans attendre', "avant qu'il ne soit trop tard",
        'dernière chance', 'ne perdez pas', 'agissez maintenant', 'deadline',
        'date limite', 'offre limitée', 'places limitées', 'ne ratez pas',
        'ne manquez pas', 'maintenant ou jamais', 'vite', 'en urgence',
        'sans délai', 'temps limité',
    ];

    /** CTA phrases (French). */
    private const CTA_PHRASES = [
        'inscrivez-vous', 'téléchargez', 'contactez-nous', 'réservez',
        'commandez', 'essayez', 'profitez', 'découvrez', 'obtenez',
        'commencez', 'cliquez', 'demandez',
    ];

    /** Inclusive language (French). */
    private const INCLUSIVE_PHRASES = [
        'ensemble', 'notre communauté', 'rejoignez', 'partagez', 'entre nous',
        'notre équipe', 'faisons', 'construisons', 'notre mission',
    ];

    /** Common acronyms to exclude from CAPS detection. */
    private const KNOWN_ACRONYMS = [
        'UE', 'USA', 'UK', 'OFII', 'VFS', 'TLS', 'ANTS', 'CPAM', 'RSI',
        'TVA', 'SCI', 'SARL', 'SAS', 'EURL', 'PDF', 'FAQ', 'SEO', 'API',
        'CTA', 'IVR', 'SMS', 'QR', 'AI', 'IA', 'CEO', 'CFO', 'RH',
    ];

    /** SOS-Expat brand tone ranges. */
    private const BRAND_RANGES = [
        'sentiment'  => ['min' => 0.1, 'max' => 0.5],
        'formality'  => ['min' => 55, 'max' => 80],
        'emotion'    => ['min' => 15, 'max' => 45],
        'urgency'    => ['min' => 10, 'max' => 35],
        'engagement' => ['min' => 40, 'max' => 75],
    ];

    /**
     * Run full tone analysis on a text.
     */
    public function analyze(string $text): array
    {
        try {
            $text = $this->stripHtml($text);

            if (empty(trim($text))) {
                return $this->emptyResult();
            }

            $textLower = mb_strtolower($text);

            $sentiment  = $this->analyzeSentiment($textLower);
            $formality  = $this->analyzeFormality($textLower);
            $emotion    = $this->analyzeEmotion($text, $textLower);
            $urgency    = $this->analyzeUrgency($textLower);
            $engagement = $this->analyzeEngagement($text, $textLower);

            $overallTone = $this->classifyTone($sentiment, $formality, $emotion, $urgency, $engagement);

            $isBrandCompliant = $this->checkBrandCompliance($sentiment, $formality, $emotion, $urgency, $engagement);

            $recommendations = $this->generateRecommendations(
                $sentiment, $formality, $emotion, $urgency, $engagement
            );

            return [
                'sentiment'          => round($sentiment, 2),
                'formality'          => round($formality, 1),
                'emotion'            => round($emotion, 1),
                'urgency'            => round($urgency, 1),
                'engagement'         => round($engagement, 1),
                'overall_tone'       => $overallTone,
                'is_brand_compliant' => $isBrandCompliant,
                'recommendations'    => $recommendations,
            ];
        } catch (\Throwable $e) {
            Log::error('Tone analysis failed', ['message' => $e->getMessage()]);

            throw $e;
        }
    }

    // ============================================================
    // Dimension analyzers
    // ============================================================

    /**
     * Sentiment: (positive - negative) / (positive + negative + 1).
     */
    private function analyzeSentiment(string $textLower): float
    {
        $positiveCount = 0;
        $negativeCount = 0;

        foreach (self::POSITIVE_WORDS as $word) {
            $positiveCount += mb_substr_count($textLower, $word);
        }

        foreach (self::NEGATIVE_WORDS as $word) {
            $negativeCount += mb_substr_count($textLower, $word);
        }

        $total = $positiveCount + $negativeCount + 1;

        return ($positiveCount - $negativeCount) / $total;
    }

    /**
     * Formality: 50 + (formal - informal) * 5, clamped 0-100.
     */
    private function analyzeFormality(string $textLower): float
    {
        $formalCount = 0;
        $informalCount = 0;

        foreach (self::FORMAL_INDICATORS as $indicator) {
            $formalCount += mb_substr_count($textLower, mb_strtolower($indicator));
        }

        foreach (self::INFORMAL_INDICATORS as $indicator) {
            $informalCount += mb_substr_count($textLower, mb_strtolower($indicator));
        }

        $score = 50 + ($formalCount - $informalCount) * 5;

        return max(0, min(100, $score));
    }

    /**
     * Emotion: exclamation marks, ALL CAPS words, emotional words, emojis.
     */
    private function analyzeEmotion(string $text, string $textLower): float
    {
        // Exclamation marks
        $exclamations = mb_substr_count($text, '!');

        // ALL CAPS words (4+ chars, excluding known acronyms)
        preg_match_all('/\b[A-Z]{4,}\b/', $text, $capsMatches);
        $capsCount = 0;
        foreach ($capsMatches[0] ?? [] as $capsWord) {
            if (!in_array($capsWord, self::KNOWN_ACRONYMS)) {
                $capsCount++;
            }
        }

        // Emotional words
        $emotionalCount = 0;
        foreach (self::EMOTIONAL_WORDS as $word) {
            $emotionalCount += mb_substr_count($textLower, $word);
        }

        // Emojis
        $emojiCount = preg_match_all(
            '/[\x{1F600}-\x{1F64F}\x{1F300}-\x{1F5FF}\x{1F680}-\x{1F6FF}\x{1F900}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]/u',
            $text
        );

        $score = ($exclamations * 10) + ($capsCount * 15) + ($emotionalCount * 8) + ($emojiCount * 12);

        return min(100, $score);
    }

    /**
     * Urgency: urgency phrases + CTA intensity.
     */
    private function analyzeUrgency(string $textLower): float
    {
        $urgentCount = 0;
        foreach (self::URGENCY_PHRASES as $phrase) {
            $urgentCount += mb_substr_count($textLower, $phrase);
        }

        $ctaCount = 0;
        foreach (self::CTA_PHRASES as $cta) {
            $ctaCount += mb_substr_count($textLower, $cta);
        }

        $score = ($urgentCount * 15) + ($ctaCount * 8);

        return min(100, $score);
    }

    /**
     * Engagement: questions, direct address, CTAs, inclusive language.
     */
    private function analyzeEngagement(string $text, string $textLower): float
    {
        // Questions
        $questions = mb_substr_count($text, '?');

        // Direct address ("vous pouvez", "votre", "n'hésitez pas")
        $directAddress = 0;
        $directPhrases = ['vous pouvez', 'votre', 'vos', "n'hésitez pas", 'pour vous'];
        foreach ($directPhrases as $phrase) {
            $directAddress += mb_substr_count($textLower, $phrase);
        }

        // CTAs
        $ctaCount = 0;
        foreach (self::CTA_PHRASES as $cta) {
            $ctaCount += mb_substr_count($textLower, $cta);
        }

        // Inclusive language
        $inclusiveCount = 0;
        foreach (self::INCLUSIVE_PHRASES as $phrase) {
            $inclusiveCount += mb_substr_count($textLower, $phrase);
        }

        $score = ($questions * 8) + ($directAddress * 3) + ($ctaCount * 10) + ($inclusiveCount * 5);

        return min(100, $score);
    }

    // ============================================================
    // Classification & compliance
    // ============================================================

    /**
     * Classify the overall tone into a named category.
     */
    private function classifyTone(float $sentiment, float $formality, float $emotion, float $urgency, float $engagement): string
    {
        if ($sentiment >= 0.1 && $sentiment <= 0.5 && $formality >= 55 && $formality <= 80 && $emotion <= 45) {
            return 'professional_optimistic';
        }

        if ($formality >= 70 && $emotion <= 25 && $urgency <= 20) {
            return 'authoritative';
        }

        if ($formality < 40 && $emotion > 40) {
            return 'casual';
        }

        if ($urgency > 60 || ($emotion > 60 && $sentiment < -0.2)) {
            return 'aggressive';
        }

        return 'neutral';
    }

    /**
     * Check if tone falls within SOS-Expat brand ranges.
     */
    private function checkBrandCompliance(float $sentiment, float $formality, float $emotion, float $urgency, float $engagement): bool
    {
        $r = self::BRAND_RANGES;

        return $sentiment >= $r['sentiment']['min'] && $sentiment <= $r['sentiment']['max']
            && $formality >= $r['formality']['min'] && $formality <= $r['formality']['max']
            && $emotion >= $r['emotion']['min'] && $emotion <= $r['emotion']['max']
            && $urgency >= $r['urgency']['min'] && $urgency <= $r['urgency']['max']
            && $engagement >= $r['engagement']['min'] && $engagement <= $r['engagement']['max'];
    }

    /**
     * Generate tone recommendations based on dimension deviations.
     */
    private function generateRecommendations(float $sentiment, float $formality, float $emotion, float $urgency, float $engagement): array
    {
        $recommendations = [];
        $r = self::BRAND_RANGES;

        if ($sentiment < $r['sentiment']['min']) {
            $recommendations[] = 'Ton trop négatif — ajouter des éléments positifs et rassurants';
        } elseif ($sentiment > $r['sentiment']['max']) {
            $recommendations[] = 'Ton trop promotionnel — équilibrer avec des faits objectifs';
        }

        if ($formality < $r['formality']['min']) {
            $recommendations[] = 'Registre trop familier — utiliser le vouvoiement et un vocabulaire professionnel';
        } elseif ($formality > $r['formality']['max']) {
            $recommendations[] = 'Registre trop formel — simplifier pour rester accessible';
        }

        if ($emotion > $r['emotion']['max']) {
            $recommendations[] = 'Trop émotionnel — réduire les points d\'exclamation et les mots sensationnels';
        } elseif ($emotion < $r['emotion']['min']) {
            $recommendations[] = 'Trop plat — ajouter un peu d\'enthousiasme mesuré';
        }

        if ($urgency > $r['urgency']['max']) {
            $recommendations[] = 'Trop pressant — adopter un ton plus informatif et moins agressif';
        }

        if ($engagement < $r['engagement']['min']) {
            $recommendations[] = 'Manque d\'engagement — ajouter des questions, des CTA et du langage inclusif';
        } elseif ($engagement > $r['engagement']['max']) {
            $recommendations[] = 'Trop de sollicitations — espacer les appels à l\'action';
        }

        return $recommendations;
    }

    // ============================================================
    // Helpers
    // ============================================================

    private function stripHtml(string $text): string
    {
        $text = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $text);
        $text = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return preg_replace('/\s+/', ' ', trim($text));
    }

    private function emptyResult(): array
    {
        return [
            'sentiment'          => 0.0,
            'formality'          => 50.0,
            'emotion'            => 0.0,
            'urgency'            => 0.0,
            'engagement'         => 0.0,
            'overall_tone'       => 'neutral',
            'is_brand_compliant' => false,
            'recommendations'    => ['Aucun texte à analyser.'],
        ];
    }
}
