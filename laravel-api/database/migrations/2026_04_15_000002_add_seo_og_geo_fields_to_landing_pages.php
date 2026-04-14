<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            // URL canonique absolue
            $table->string('canonical_url', 500)->nullable()->after('slug');

            // OpenGraph
            $table->string('og_locale', 10)->nullable()->after('canonical_url');
            // ex: fr_FR, en_US, ar_SA, zh_CN, hi_IN, ru_RU, de_DE, es_ES, pt_PT
            $table->string('og_type', 30)->default('WebPage')->after('og_locale');
            $table->string('og_url', 500)->nullable()->after('og_type');
            $table->string('og_site_name', 100)->default('SOS-Expat & Travelers')->after('og_url');
            $table->string('twitter_card', 30)->default('summary_large_image')->after('og_site_name');

            // Content language (code BCP-47: fr, en, ar, zh, hi, etc.)
            $table->string('content_language', 5)->nullable()->after('twitter_card');

            // Geo metadata (pour référencement local / Google)
            $table->string('geo_region', 5)->nullable()->after('content_language');
            // ex: TH, FR, AE — code pays ISO 3166-1 alpha-2
            $table->string('geo_placename', 200)->nullable()->after('geo_region');
            // Nom du pays dans la langue de la page
            $table->string('geo_position', 50)->nullable()->after('geo_placename');
            // "lat;lon" ex: "13.7563;100.5018"
            $table->string('icbm', 50)->nullable()->after('geo_position');
            // "lat, lon" ex: "13.7563, 100.5018"

            // Audit SEO
            $table->timestamp('last_reviewed_at')->nullable()->after('published_at');
        });
    }

    public function down(): void
    {
        Schema::table('landing_pages', function (Blueprint $table) {
            $table->dropColumn([
                'canonical_url',
                'og_locale', 'og_type', 'og_url', 'og_site_name', 'twitter_card',
                'content_language',
                'geo_region', 'geo_placename', 'geo_position', 'icbm',
                'last_reviewed_at',
            ]);
        });
    }
};
