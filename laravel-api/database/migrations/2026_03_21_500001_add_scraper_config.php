<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Add scraper toggle per contact type
        Schema::table('contact_types', function (Blueprint $table) {
            $table->boolean('scraper_enabled')->default(false)->after('is_active');
        });

        // 2. Enable scraper by default for types where it makes sense
        $scrapableTypes = [
            'school', 'erasmus', 'association', 'press', 'backlink',
            'real_estate', 'translator', 'travel_agency', 'insurer',
            'enterprise', 'partner', 'lawyer', 'job_board',
        ];
        DB::table('contact_types')
            ->whereIn('value', $scrapableTypes)
            ->update(['scraper_enabled' => true]);

        // Social platforms = scraper disabled (useless on youtube/instagram/tiktok)
        // influenceur, tiktoker, youtuber, instagramer, blogger, chatter, group_admin
        // → already false by default

        // 3. Global settings table for scraper on/off + config
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->text('value');
            $table->timestamps();
        });

        // Global scraper toggle (default: off until user enables it)
        DB::table('settings')->insert([
            'key'        => 'scraper_enabled',
            'value'      => 'false',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('contact_types', function (Blueprint $table) {
            $table->dropColumn('scraper_enabled');
        });
        Schema::dropIfExists('settings');
    }
};
