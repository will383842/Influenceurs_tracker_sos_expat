<?php

namespace Database\Seeders;

use App\Models\GenerationPreset;
use Illuminate\Database\Seeder;

class GenerationPresetSeeder extends Seeder
{
    public function run(): void
    {
        $presets = [
            [
                'name' => 'Standard (depuis sources)',
                'description' => 'Article standard basé sur les sources scrapées, avec FAQ, images Unsplash, liens internes et affiliés',
                'content_type' => 'article',
                'config' => [
                    'tone' => 'professional',
                    'length' => 'long',
                    'faq_count' => 10,
                    'research' => true,
                    'image_source' => 'unsplash',
                    'internal_links' => true,
                    'affiliate_links' => true,
                    'generate_faq' => true,
                    'auto_internal_links' => true,
                    'auto_affiliate_links' => true,
                ],
                'is_default' => true,
            ],
            [
                'name' => 'Guide pays complet',
                'description' => 'Guide exhaustif sur un pays (3000+ mots), 12 FAQ, recherche approfondie, pour pages pilier',
                'content_type' => 'guide',
                'config' => [
                    'tone' => 'expert',
                    'length' => 'extra_long',
                    'faq_count' => 12,
                    'research' => true,
                    'image_source' => 'unsplash',
                    'internal_links' => true,
                    'affiliate_links' => true,
                    'generate_faq' => true,
                    'auto_internal_links' => true,
                    'auto_affiliate_links' => true,
                    'target_words' => '3000-4000',
                ],
                'is_default' => false,
            ],
            [
                'name' => 'FAQ thématique',
                'description' => 'Article orienté FAQ avec 15 questions, ton accessible, longueur moyenne',
                'content_type' => 'article',
                'config' => [
                    'tone' => 'friendly',
                    'length' => 'medium',
                    'faq_count' => 15,
                    'research' => true,
                    'image_source' => 'unsplash',
                    'internal_links' => true,
                    'affiliate_links' => false,
                    'generate_faq' => true,
                    'auto_internal_links' => true,
                    'auto_affiliate_links' => false,
                ],
                'is_default' => false,
            ],
            [
                'name' => 'Actualité expat',
                'description' => 'Article court sur une actualité récente (1500-2000 mots), 6 FAQ, publication rapide',
                'content_type' => 'news',
                'config' => [
                    'tone' => 'professional',
                    'length' => 'medium',
                    'faq_count' => 6,
                    'research' => true,
                    'image_source' => 'unsplash',
                    'internal_links' => true,
                    'affiliate_links' => false,
                    'generate_faq' => true,
                    'auto_internal_links' => true,
                    'auto_affiliate_links' => false,
                    'target_words' => '1500-2000',
                ],
                'is_default' => false,
            ],
            [
                'name' => 'Comparatif pays',
                'description' => 'Article comparatif entre 3+ pays ou services, avec tableau et données chiffrées',
                'content_type' => 'comparative',
                'config' => [
                    'tone' => 'professional',
                    'length' => 'long',
                    'faq_count' => 8,
                    'research' => true,
                    'image_source' => 'unsplash',
                    'internal_links' => true,
                    'affiliate_links' => true,
                    'generate_faq' => true,
                    'auto_internal_links' => true,
                    'auto_affiliate_links' => true,
                    'entities_count' => 3,
                    'include_comparison_table' => true,
                ],
                'is_default' => false,
            ],
        ];

        foreach ($presets as $preset) {
            GenerationPreset::updateOrCreate(
                ['name' => $preset['name']],
                $preset
            );
        }
    }
}
