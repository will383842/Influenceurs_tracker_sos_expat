<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
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
     * Also add source_slug to generation_source_items so each item knows its input_quality.
     */
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('source_slug', 50)->nullable()->after('content_type')->index();
            $table->string('input_quality', 20)->nullable()->default('title_only')->after('source_slug');
            // Valid values: full_content, title_only, structured
        });

        // Also tag source items with their input quality
        Schema::table('generation_source_items', function (Blueprint $table) {
            $table->string('input_quality', 20)->default('title_only')->after('source_type');
            // full_content = scraped article with HTML/text content
            // title_only   = manually added title, no scraped content
            // structured   = structured data (CSV, DB, JSON)
        });

        // Backfill input_quality on existing source items based on word_count
        // Items with actual content (word_count > 200) are 'full_content'
        DB::statement("
            UPDATE generation_source_items
            SET input_quality = CASE
                WHEN word_count > 200 THEN 'full_content'
                WHEN source_type = 'pillar' THEN 'structured'
                ELSE 'title_only'
            END
        ");
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn(['source_slug', 'input_quality']);
        });

        Schema::table('generation_source_items', function (Blueprint $table) {
            $table->dropColumn('input_quality');
        });
    }
};
