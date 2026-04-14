<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Expand source_type enum to include all 14 content angles
        DB::statement("ALTER TABLE linkedin_posts MODIFY COLUMN source_type ENUM(
            'article',
            'faq',
            'sondage',
            'news',
            'hot_take',
            'myth',
            'poll',
            'serie',
            'reactive',
            'milestone',
            'partner_story',
            'counter_intuition',
            'tip',
            'case_study'
        ) NOT NULL DEFAULT 'article'");

        // 2. Add 'generating' status to the status enum
        DB::statement("ALTER TABLE linkedin_posts MODIFY COLUMN status ENUM(
            'generating',
            'draft',
            'scheduled',
            'published',
            'failed'
        ) NOT NULL DEFAULT 'draft'");

        // 3. Add first_comment column (text for the auto-comment posted 3 min after publication)
        DB::statement("ALTER TABLE linkedin_posts ADD COLUMN first_comment TEXT NULL AFTER error_message");

        // 4. Add featured_image_url for posts that include an image
        DB::statement("ALTER TABLE linkedin_posts ADD COLUMN featured_image_url VARCHAR(500) NULL AFTER first_comment");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE linkedin_posts DROP COLUMN IF EXISTS featured_image_url");
        DB::statement("ALTER TABLE linkedin_posts DROP COLUMN IF EXISTS first_comment");

        DB::statement("ALTER TABLE linkedin_posts MODIFY COLUMN status ENUM(
            'draft','scheduled','published','failed'
        ) NOT NULL DEFAULT 'draft'");

        DB::statement("ALTER TABLE linkedin_posts MODIFY COLUMN source_type ENUM(
            'article','faq','testimonial','news','case_study','tip'
        ) NOT NULL DEFAULT 'article'");
    }
};
