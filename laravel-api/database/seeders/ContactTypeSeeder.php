<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the 20 default contact types (19 original + Erasmus).
 * Uses updateOrCreate so it's safe to re-run.
 */
class ContactTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['value' => 'school',         'label' => 'Écoles',              'icon' => '🏫', 'color' => '#10B981', 'sort_order' => 1],
            ['value' => 'erasmus',        'label' => 'Écoles Erasmus',      'icon' => '🎓', 'color' => '#2563EB', 'sort_order' => 2],
            ['value' => 'chatter',        'label' => 'Chatters',            'icon' => '💬', 'color' => '#FF6B6B', 'sort_order' => 3],
            ['value' => 'tiktoker',       'label' => 'TikTokeurs',          'icon' => '🎵', 'color' => '#FF0050', 'sort_order' => 4],
            ['value' => 'youtuber',       'label' => 'YouTubeurs',          'icon' => '🎬', 'color' => '#FF0000', 'sort_order' => 5],
            ['value' => 'instagramer',    'label' => 'Instagrameurs',       'icon' => '📸', 'color' => '#E1306C', 'sort_order' => 6],
            ['value' => 'influenceur',    'label' => 'Influenceurs',        'icon' => '✨', 'color' => '#FFD60A', 'sort_order' => 7],
            ['value' => 'blogger',        'label' => 'Blogueurs',           'icon' => '📰', 'color' => '#A855F7', 'sort_order' => 8],
            ['value' => 'backlink',       'label' => 'Backlinks',           'icon' => '🔗', 'color' => '#F59E0B', 'sort_order' => 9],
            ['value' => 'association',    'label' => 'Associations',        'icon' => '🤝', 'color' => '#EC4899', 'sort_order' => 10],
            ['value' => 'travel_agency',  'label' => 'Agences voyage',      'icon' => '✈️', 'color' => '#06B6D4', 'sort_order' => 11],
            ['value' => 'real_estate',    'label' => 'Agents immobiliers',  'icon' => '🏠', 'color' => '#84CC16', 'sort_order' => 12],
            ['value' => 'translator',     'label' => 'Traducteurs',         'icon' => '🌐', 'color' => '#0EA5E9', 'sort_order' => 13],
            ['value' => 'insurer',        'label' => 'Assureurs/B2B',       'icon' => '🛡️', 'color' => '#3B82F6', 'sort_order' => 14],
            ['value' => 'enterprise',     'label' => 'Entreprises',         'icon' => '🏢', 'color' => '#14B8A6', 'sort_order' => 15],
            ['value' => 'press',          'label' => 'Presse',              'icon' => '📺', 'color' => '#E11D48', 'sort_order' => 16],
            ['value' => 'partner',        'label' => 'Partenariats',        'icon' => '🏛️', 'color' => '#D97706', 'sort_order' => 17],
            ['value' => 'lawyer',         'label' => 'Avocats',             'icon' => '⚖️', 'color' => '#8B5CF6', 'sort_order' => 18],
            ['value' => 'job_board',      'label' => 'Sites emploi',        'icon' => '💼', 'color' => '#78716C', 'sort_order' => 19],
            ['value' => 'group_admin',    'label' => 'Group Admins',        'icon' => '👥', 'color' => '#F472B6', 'sort_order' => 20],
        ];

        foreach ($types as $type) {
            DB::table('contact_types')->updateOrInsert(
                ['value' => $type['value']],
                array_merge($type, [
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }
    }
}
