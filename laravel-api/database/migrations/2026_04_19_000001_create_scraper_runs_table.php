<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('scraper_runs')) {
            return;
        }

        Schema::create('scraper_runs', function (Blueprint $table) {
            $table->id();
            $table->string('scraper_name', 100);
            $table->string('status', 30);
            // ok | skipped_no_ia | rate_limited | circuit_broken | error | running
            $table->string('country', 100)->nullable();
            $table->unsignedInteger('contacts_found')->default(0);
            $table->unsignedInteger('contacts_new')->default(0);
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->text('error_message')->nullable();
            $table->boolean('requires_perplexity')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['scraper_name', 'started_at']);
            $table->index('started_at'); // pour ScrapersDailyReportCommand (WHERE started_at >= NOW() - 1 DAY)
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scraper_runs');
    }
};
