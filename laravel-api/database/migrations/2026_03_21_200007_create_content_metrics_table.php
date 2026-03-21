<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION: Content Engine metrics (from Mission Control's SEO tracking).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();

            // Manual metrics (from Mission Control)
            $table->unsignedSmallInteger('landing_pages')->default(0);
            $table->unsignedSmallInteger('articles')->default(0);
            $table->unsignedInteger('indexed_pages')->default(0);
            $table->unsignedSmallInteger('top10_positions')->default(0);
            $table->unsignedSmallInteger('position_zero')->default(0);
            $table->unsignedSmallInteger('ai_cited')->default(0);
            $table->unsignedInteger('daily_visits')->default(0);
            $table->unsignedSmallInteger('calls_generated')->default(0);
            $table->unsignedInteger('revenue_cents')->default(0);

            // API-sourced data (future: Google Search Console, GA4)
            $table->json('search_console_data')->nullable();
            $table->json('analytics_data')->nullable();

            $table->timestamps();

            $table->index('date', 'idx_content_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_metrics');
    }
};
