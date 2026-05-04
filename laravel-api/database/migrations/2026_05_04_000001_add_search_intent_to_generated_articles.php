<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Add search_intent column to generated_articles.
 *
 * Persist the intent that drove the LLM prompt so it can be (a) queried for
 * analytics, (b) transmitted downstream to the public blog, (c) reused on
 * regeneration.
 *
 * Allowed values (free-form VARCHAR for forward compat — no enum/check):
 *   informational, urgency, transactional, commercial_investigation, local
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('generated_articles', 'search_intent')) {
            Schema::table('generated_articles', function (Blueprint $table) {
                $table->string('search_intent', 30)->nullable()->after('content_type');
            });
        }

        // Composite index for analytics queries (content_type + intent)
        $indexExists = collect(DB::select("
            SELECT 1 FROM pg_indexes
            WHERE tablename = 'generated_articles'
              AND indexname = 'generated_articles_ctype_intent_idx'
        "))->isNotEmpty();

        if (!$indexExists) {
            DB::statement('CREATE INDEX generated_articles_ctype_intent_idx ON generated_articles (content_type, search_intent)');
        }
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS generated_articles_ctype_intent_idx');

        if (Schema::hasColumn('generated_articles', 'search_intent')) {
            Schema::table('generated_articles', function (Blueprint $table) {
                $table->dropColumn('search_intent');
            });
        }
    }
};
