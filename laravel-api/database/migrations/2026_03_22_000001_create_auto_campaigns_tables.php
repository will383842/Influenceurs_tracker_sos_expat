<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ============================================================
        // Campaign = one full sweep (all selected country/type combos)
        // ============================================================
        Schema::create('auto_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('status', 20)->default('pending'); // pending, running, paused, completed, cancelled
            $table->json('contact_types');   // ["school","association",...]
            $table->json('countries');       // ["Australie","UK",...]
            $table->json('languages');       // ["fr","en",...]

            // Rate limiting config
            $table->unsignedInteger('delay_between_tasks_seconds')->default(300);   // 5 min between AI research tasks
            $table->unsignedInteger('delay_between_retries_seconds')->default(600); // 10 min before retry
            $table->unsignedInteger('max_retries')->default(3);

            // Progress counters
            $table->unsignedInteger('tasks_total')->default(0);
            $table->unsignedInteger('tasks_completed')->default(0);
            $table->unsignedInteger('tasks_failed')->default(0);
            $table->unsignedInteger('tasks_skipped')->default(0);
            $table->unsignedInteger('contacts_found_total')->default(0);
            $table->unsignedInteger('contacts_imported_total')->default(0);
            $table->unsignedInteger('total_cost_cents')->default(0);

            // Safety: circuit breaker
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('max_consecutive_failures')->default(5); // auto-pause after 5 consecutive failures

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('last_task_at')->nullable(); // for rate limiting

            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('status');
        });

        // ============================================================
        // Task = one country/type/language combo within a campaign
        // ============================================================
        Schema::create('auto_campaign_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('auto_campaigns')->cascadeOnDelete();

            $table->string('contact_type', 50);
            $table->string('country', 100);
            $table->string('language', 10);

            $table->string('status', 20)->default('pending'); // pending, running, completed, failed, skipped
            $table->unsignedTinyInteger('attempt')->default(0);

            // Link to actual AI research session
            $table->foreignId('ai_session_id')->nullable()->constrained('ai_research_sessions')->nullOnDelete();

            // Results
            $table->unsignedInteger('contacts_found')->default(0);
            $table->unsignedInteger('contacts_imported')->default(0);
            $table->text('error_message')->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->unsignedSmallInteger('priority')->default(100); // lower = higher priority

            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auto_campaign_tasks');
        Schema::dropIfExists('auto_campaigns');
    }
};
