<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add source_slug and input_quality to generated_articles.
 *
 * source_slug:   which of the 14 generation sources produced this article
 *                (fiche-pays, fiche-villes, qa, fiches-pratiques, temoignages,
 *                 annuaires, comparatifs, affiliation, chatters, admin-groups,
 *                 bloggeurs, part-avocats, part-expat, besoins-reels)
 *
 * input_quality: what raw material was available to the generator
 *                full_content → scraping complet (Fiche Pays, Q&A, Fiches Pratiques)
 *                title_only   → titre seul suggéré (Chatters, Bloggeurs, Témoignages)
 *                structured   → données structurées (Annuaires, Comparatifs auto)
 *
 * Also add input_quality to generation_source_items so each item tracks its quality.
 *
 * All additions guarded with hasColumn() so this migration is safe to re-run
 * on databases that already have some of these columns via direct SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ── generated_articles ────────────────────────────────────────────────
        Schema::table('generated_articles', function (Blueprint $table) {
            if (!Schema::hasColumn('generated_articles', 'source_slug')) {
                $table->string('source_slug', 50)->nullable()->after('content_type')->index();
            }
            if (!Schema::hasColumn('generated_articles', 'input_quality')) {
                $table->string('input_quality', 20)->nullable()->default('title_only')->after('source_slug');
                // Valid values: full_content, title_only, structured
            }
        });

        // ── generation_source_items ───────────────────────────────────────────
        if (Schema::hasTable('generation_source_items')) {
            Schema::table('generation_source_items', function (Blueprint $table) {
                if (!Schema::hasColumn('generation_source_items', 'input_quality')) {
                    $table->string('input_quality', 20)->default('title_only')->after('source_type');
                    // full_content = scraped article with HTML/text content
                    // title_only   = manually added title, no scraped content
                    // structured   = structured data (CSV, DB, JSON)
                }
            });

            // Backfill input_quality on existing source items based on word_count
            DB::statement("
                UPDATE generation_source_items
                SET input_quality = CASE
                    WHEN word_count > 200 THEN 'full_content'
                    WHEN source_type = 'pillar' THEN 'structured'
                    ELSE 'title_only'
                END
                WHERE input_quality = 'title_only'
            ");
        }
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            if (Schema::hasColumn('generated_articles', 'source_slug')) {
                $table->dropColumn('source_slug');
            }
            if (Schema::hasColumn('generated_articles', 'input_quality')) {
                $table->dropColumn('input_quality');
            }
        });

        if (Schema::hasTable('generation_source_items')) {
            Schema::table('generation_source_items', function (Blueprint $table) {
                if (Schema::hasColumn('generation_source_items', 'input_quality')) {
                    $table->dropColumn('input_quality');
                }
            });
        }
    }
};
