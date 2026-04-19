<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scraper_rotation_state')) {
            return;
        }

        Schema::create('scraper_rotation_state', function (Blueprint $table) {
            $table->id();
            $table->string('scraper_name', 100)->unique();
            $table->string('last_country', 100)->nullable();
            $table->timestamp('last_ran_at')->nullable();
            // FIFO queue of remaining countries for the current cycle.
            // When empty, ScraperRotationService refills from the source list.
            $table->json('country_queue')->nullable();
            $table->json('recent_countries')->nullable();
            // Rows visited in the last 24h (used to avoid re-scraping same country 2×/day)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_rotation_state');
    }
};
