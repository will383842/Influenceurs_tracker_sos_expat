<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Raise the country campaign target from 100 to 220 articles per country
        // (200 SEO topical articles + 20 brand SOS-Expat.com articles).
        // Conditional on existing value to stay idempotent and safe to re-run.
        DB::table('content_orchestrator_config')
            ->where('campaign_articles_per_country', 100)
            ->update([
                'campaign_articles_per_country' => 220,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('content_orchestrator_config')
            ->where('campaign_articles_per_country', 220)
            ->update([
                'campaign_articles_per_country' => 100,
                'updated_at' => now(),
            ]);
    }
};
