<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds 'pinterest' to the platform CHECK constraint on social_*.
 * The original migrations defined the constraint with only 4 platforms
 * (linkedin/facebook/threads/instagram). Pinterest is added as a 5th.
 */
return new class extends Migration
{
    public function up(): void
    {
        // social_posts
        DB::statement('ALTER TABLE social_posts DROP CONSTRAINT IF EXISTS social_posts_platform_check');
        DB::statement("ALTER TABLE social_posts ADD CONSTRAINT social_posts_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram','pinterest']::text[])
        )");

        // social_tokens
        DB::statement('ALTER TABLE social_tokens DROP CONSTRAINT IF EXISTS social_tokens_platform_check');
        DB::statement("ALTER TABLE social_tokens ADD CONSTRAINT social_tokens_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram','pinterest']::text[])
        )");

        // social_post_comments
        DB::statement('ALTER TABLE social_post_comments DROP CONSTRAINT IF EXISTS social_post_comments_platform_check');
        DB::statement("ALTER TABLE social_post_comments ADD CONSTRAINT social_post_comments_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram','pinterest']::text[])
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE social_posts DROP CONSTRAINT IF EXISTS social_posts_platform_check');
        DB::statement("ALTER TABLE social_posts ADD CONSTRAINT social_posts_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram']::text[])
        )");

        DB::statement('ALTER TABLE social_tokens DROP CONSTRAINT IF EXISTS social_tokens_platform_check');
        DB::statement("ALTER TABLE social_tokens ADD CONSTRAINT social_tokens_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram']::text[])
        )");

        DB::statement('ALTER TABLE social_post_comments DROP CONSTRAINT IF EXISTS social_post_comments_platform_check');
        DB::statement("ALTER TABLE social_post_comments ADD CONSTRAINT social_post_comments_platform_check CHECK (
            platform::text = ANY (ARRAY['linkedin','facebook','threads','instagram']::text[])
        )");
    }
};
