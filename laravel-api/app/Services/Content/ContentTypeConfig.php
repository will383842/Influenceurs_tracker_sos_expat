<?php

namespace App\Services\Content;

class ContentTypeConfig
{
    /**
     * Get AI configuration for each content type.
     * Different types get different AI models, prompts, and depth.
     */
    public static function get(string $type): array
    {
        return match ($type) {
            // PILLAR ARTICLES (fiches pays, guides complets)
            // Maximum quality: GPT-4o for content + Perplexity for research + longest format
            'guide', 'pillar' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.6,
                'min_words' => 4000,
                'max_words' => 7000,
                'target_words' => 5000,
                'target_words_range' => '4000-7000',
                'length' => 'extra_long',
                'faq_count' => 12,
                'max_tokens_content' => 16384,
                'max_tokens_title' => 100,
                'internal_links' => 8,
                'external_links' => 5,
                'images_count' => 4,
                'featured_snippet' => true,
                'comparison_table' => true,
                'numbered_steps' => true,
                'research_depth' => 'deep',
                'quality_threshold' => 90,
                'h2_count' => [8, 12],
                'include_charts_data' => true,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Cet article doit etre la REFERENCE MONDIALE sur ce sujet. "
                    . "Il doit etre plus complet, plus detaille et plus utile que TOUT ce qui existe sur le web. "
                    . "Inclure des donnees chiffrees precises, des tableaux comparatifs, des listes d'etapes, "
                    . "des conseils pratiques uniques, et des avertissements importants.",
            ],

            // NORMAL ARTICLES (thematiques, pratiques)
            'article' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.7,
                'min_words' => 2000,
                'max_words' => 3000,
                'target_words' => 2500,
                'target_words_range' => '2000-3000',
                'length' => 'long',
                'faq_count' => 8,
                'max_tokens_content' => 8000,
                'max_tokens_title' => 100,
                'internal_links' => 6,
                'external_links' => 3,
                'images_count' => 2,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => true,
                'research_depth' => 'standard',
                'quality_threshold' => 85,
                'h2_count' => [6, 8],
                'include_charts_data' => false,
                'include_key_figures' => false,
                'eeat_signals' => true,
                'prompt_suffix' => "Article informatif et pratique de haute qualite.",
            ],

            // COMPARATIVES (tableaux, donnees structurees)
            'comparative' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.5,
                'min_words' => 2500,
                'max_words' => 4000,
                'target_words' => 3000,
                'target_words_range' => '2500-4000',
                'length' => 'long',
                'faq_count' => 6,
                'max_tokens_content' => 8000,
                'max_tokens_title' => 100,
                'internal_links' => 5,
                'external_links' => 4,
                'images_count' => 1,
                'featured_snippet' => true,
                'comparison_table' => true,
                'numbered_steps' => false,
                'research_depth' => 'deep',
                'quality_threshold' => 85,
                'h2_count' => [5, 8],
                'include_charts_data' => true,
                'include_key_figures' => true,
                'eeat_signals' => true,
                'prompt_suffix' => "Article COMPARATIF avec OBLIGATOIREMENT : "
                    . "1) Au moins 2 tableaux <table> comparatifs detailles avec <thead> et <tbody>, "
                    . "2) Des donnees chiffrees precises pour chaque entite comparee, "
                    . "3) Un bloc 'Chiffres cles' avec les donnees importantes en <strong>, "
                    . "4) Un resume 'Verdict' en fin d'article avec recommandations par profil d'expatrie.",
            ],

            // Q&A (reponses directes, featured snippets)
            'qa' => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.5,
                'min_words' => 800,
                'max_words' => 2000,
                'target_words' => 1200,
                'target_words_range' => '800-2000',
                'length' => 'medium',
                'faq_count' => 0,
                'max_tokens_content' => 3000,
                'max_tokens_title' => 80,
                'internal_links' => 3,
                'external_links' => 2,
                'images_count' => 1,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => false,
                'research_depth' => 'light',
                'quality_threshold' => 80,
                'h2_count' => [3, 5],
                'include_charts_data' => false,
                'include_key_figures' => false,
                'eeat_signals' => true,
                'prompt_suffix' => "Page Q&A avec reponse directe de 40-60 mots (featured snippet) "
                    . "suivie d'une reponse detaillee structuree.",
            ],

            // DEFAULT
            default => [
                'model' => 'gpt-4o',
                'research_model' => 'sonar',
                'temperature' => 0.7,
                'min_words' => 2000,
                'max_words' => 3000,
                'target_words' => 2500,
                'target_words_range' => '2000-3000',
                'length' => 'long',
                'faq_count' => 8,
                'max_tokens_content' => 8000,
                'max_tokens_title' => 100,
                'internal_links' => 6,
                'external_links' => 3,
                'images_count' => 2,
                'featured_snippet' => true,
                'comparison_table' => false,
                'numbered_steps' => true,
                'research_depth' => 'standard',
                'quality_threshold' => 85,
                'h2_count' => [6, 8],
                'include_charts_data' => false,
                'include_key_figures' => false,
                'eeat_signals' => true,
                'prompt_suffix' => '',
            ],
        };
    }
}
