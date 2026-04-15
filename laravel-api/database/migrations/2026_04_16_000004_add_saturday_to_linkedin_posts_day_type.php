<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Add 'saturday' to linkedin_posts.day_type constraint.
 * New editorial calendar: Mon / Wed / Fri / Sat (4 posts/week).
 * Saturday slot: 09:00 UTC (11:00 Paris) — weekend relaxed audience.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE linkedin_posts DROP CONSTRAINT IF EXISTS linkedin_posts_day_type_check');
        DB::statement("ALTER TABLE linkedin_posts ADD CONSTRAINT linkedin_posts_day_type_check CHECK (
            day_type::text = ANY (ARRAY[
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'
            ]::text[])
        )");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE linkedin_posts DROP CONSTRAINT IF EXISTS linkedin_posts_day_type_check');
        DB::statement("ALTER TABLE linkedin_posts ADD CONSTRAINT linkedin_posts_day_type_check CHECK (
            day_type::text = ANY (ARRAY[
                'monday', 'tuesday', 'wednesday', 'thursday', 'friday'
            ]::text[])
        )");
    }
};
