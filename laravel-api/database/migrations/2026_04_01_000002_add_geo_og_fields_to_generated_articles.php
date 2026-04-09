<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->string('og_type', 30)->default('article')->after('og_description');
            $table->string('og_locale', 10)->nullable()->after('og_type');
            $table->string('og_url', 500)->nullable()->after('og_locale');
            $table->string('og_site_name', 100)->default('SOS-Expat & Travelers')->after('og_url');
            $table->string('twitter_card', 30)->default('summary_large_image')->after('og_site_name');
            $table->string('geo_region', 5)->nullable()->after('twitter_card');
            $table->string('geo_placename', 200)->nullable()->after('geo_region');
            $table->string('geo_position', 50)->nullable()->after('geo_placename');
            $table->string('icbm', 50)->nullable()->after('geo_position');
            $table->string('meta_keywords', 500)->nullable()->after('icbm');
            $table->string('content_language', 5)->nullable()->after('meta_keywords');
            $table->timestamp('last_reviewed_at')->nullable()->after('content_language');
        });
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn([
                'og_type', 'og_locale', 'og_url', 'og_site_name', 'twitter_card',
                'geo_region', 'geo_placename', 'geo_position', 'icbm',
                'meta_keywords', 'content_language', 'last_reviewed_at',
            ]);
        });
    }
};
