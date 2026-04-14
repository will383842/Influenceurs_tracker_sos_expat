<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Expand check constraints on linkedin_posts to match current codebase needs.
 * - status: add 'generating' and 'pending_confirm'
 * - source_type: add all 14 supported source types
 */
return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate status constraint
        DB::statement('ALTER TABLE linkedin_posts DROP CONSTRAINT IF EXISTS linkedin_posts_status_check');
        DB::statement("ALTER TABLE linkedin_posts ADD CONSTRAINT linkedin_posts_status_check CHECK (
            status::text = ANY (ARRAY[
                'generating', 'draft', 'scheduled', 'pending_confirm', 'published', 'failed'
            ]::text[])
        )");

        // Drop and recreate source_type constraint (14 types)
        DB::statement('ALTER TABLE linkedin_posts DROP CONSTRAINT IF EXISTS linkedin_posts_source_type_check');
        DB::statement("ALTER TABLE linkedin_posts ADD CONSTRAINT linkedin_posts_source_type_check CHECK (
            source_type::text = ANY (ARRAY[
                'article', 'faq', 'sondage', 'hot_take', 'myth', 'poll', 'serie',
                'reactive', 'milestone', 'partner_story', 'counter_intuition',
                'tip', 'news', 'case_study'
            ]::text[])
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE linkedin_posts DROP CONSTRAINT IF EXISTS linkedin_posts_status_check');
        DB::statement("ALTER TABLE linkedin_posts ADD CONSTRAINT linkedin_posts_status_check CHECK (
            status::text = ANY (ARRAY['draft', 'scheduled', 'published', 'failed']::text[])
        )");

        DB::statement('ALTER TABLE linkedin_posts DROP CONSTRAINT IF EXISTS linkedin_posts_source_type_check');
        DB::statement("ALTER TABLE linkedin_posts ADD CONSTRAINT linkedin_posts_source_type_check CHECK (
            source_type::text = ANY (ARRAY['article', 'faq', 'testimonial', 'news', 'case_study', 'tip']::text[])
        )");
    }
};
