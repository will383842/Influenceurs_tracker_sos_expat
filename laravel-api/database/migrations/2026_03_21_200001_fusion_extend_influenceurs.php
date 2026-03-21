<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION Mission Control + Influenceurs Tracker
 * Extend influenceurs table with Mission Control fields + new pipeline statuses.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Convert status from enum to varchar (allows flexible statuses)
        DB::statement("ALTER TABLE influenceurs ALTER COLUMN status TYPE VARCHAR(50) USING status::VARCHAR");
        DB::statement("ALTER TABLE influenceurs ALTER COLUMN status SET DEFAULT 'new'");

        // 2. Add new columns from Mission Control
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->string('company', 255)->nullable()->after('name');
            $table->string('position', 255)->nullable()->after('company');
            $table->string('website_url', 500)->nullable()->after('profile_url_domain');
            $table->integer('deal_value_cents')->default(0)->after('status');
            $table->unsignedTinyInteger('deal_probability')->default(0)->after('deal_value_cents');
            $table->date('expected_close_date')->nullable()->after('deal_probability');
            $table->unsignedSmallInteger('score')->default(0)->after('tags');
            $table->string('source', 100)->nullable()->after('score');
            $table->string('timezone', 50)->nullable()->after('language');
        });

        // 3. Extend contact_type values — drop old CHECK constraint if it exists
        // The contact_type column was already added as varchar, no constraint to drop

        // 4. Add composite indexes for common filter combinations
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->index(['contact_type', 'country', 'status'], 'idx_inf_type_country_status');
            $table->index(['score'], 'idx_inf_score');
            $table->index(['source'], 'idx_inf_source');
            $table->index(['deal_value_cents'], 'idx_inf_deal_value');
        });
    }

    public function down(): void
    {
        Schema::table('influenceurs', function (Blueprint $table) {
            $table->dropIndex('idx_inf_type_country_status');
            $table->dropIndex('idx_inf_score');
            $table->dropIndex('idx_inf_source');
            $table->dropIndex('idx_inf_deal_value');

            $table->dropColumn([
                'company', 'position', 'website_url',
                'deal_value_cents', 'deal_probability', 'expected_close_date',
                'score', 'source', 'timezone',
            ]);
        });

        DB::statement("ALTER TABLE influenceurs ALTER COLUMN status TYPE VARCHAR(50)");
    }
};
