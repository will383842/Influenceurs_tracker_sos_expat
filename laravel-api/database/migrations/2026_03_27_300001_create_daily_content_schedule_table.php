<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Daily content generation schedule configuration
        Schema::create('daily_content_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->default('default');
            $table->boolean('is_active')->default(true);
            $table->integer('pillar_articles_per_day')->default(2);
            $table->integer('normal_articles_per_day')->default(5);
            $table->integer('qa_per_day')->default(10);
            $table->integer('comparatives_per_day')->default(2);
            $table->jsonb('custom_titles')->nullable();
            $table->integer('publish_per_day')->default(10);
            $table->integer('publish_start_hour')->default(7);
            $table->integer('publish_end_hour')->default(22);
            $table->boolean('publish_irregular')->default(true);
            $table->string('target_country', 100)->nullable();
            $table->string('target_category', 50)->nullable();
            $table->integer('min_quality_score')->default(85);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('is_active');
            $table->unique('name');
        });

        // Daily content generation logs — tracks what was generated each day
        Schema::create('daily_content_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('daily_content_schedules')->cascadeOnDelete();
            $table->date('date');
            $table->integer('pillar_generated')->default(0);
            $table->integer('normal_generated')->default(0);
            $table->integer('qa_generated')->default(0);
            $table->integer('comparatives_generated')->default(0);
            $table->integer('custom_generated')->default(0);
            $table->integer('published')->default(0);
            $table->integer('total_cost_cents')->default(0);
            $table->jsonb('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['schedule_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_content_logs');
        Schema::dropIfExists('daily_content_schedules');
    }
};
