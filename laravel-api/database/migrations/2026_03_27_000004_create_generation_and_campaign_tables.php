<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generation logs (polymorphic)
        Schema::create('generation_logs', function (Blueprint $table) {
            $table->id();
            $table->string('loggable_type');
            $table->unsignedBigInteger('loggable_id');
            $table->string('phase', 50); // validate, research, title, excerpt, content, faq, meta, jsonld, internal_links, external_links, affiliate_links, images, slugs, quality, translations
            $table->string('status', 20); // pending, running, completed, failed, skipped
            $table->text('message')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->integer('cost_cents')->default(0);
            $table->integer('duration_ms')->default(0);
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['loggable_type', 'loggable_id']);
            $table->index('phase');
            $table->index('status');
        });

        // Content generation campaigns
        Schema::create('content_generation_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->text('description')->nullable();
            $table->string('campaign_type', 50); // country_coverage, thematic, pillar_cluster, comparative_series, custom
            $table->jsonb('config'); // {country, themes, languages, articles_per_day, preset_id, etc.}
            $table->string('status', 20)->default('draft'); // draft, running, paused, completed, cancelled
            $table->integer('total_items')->default(0);
            $table->integer('completed_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->integer('total_cost_cents')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
            $table->index('campaign_type');
        });

        // Individual items within a campaign
        Schema::create('content_campaign_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('content_generation_campaigns')->onDelete('cascade');
            $table->string('itemable_type')->nullable(); // morph to generated_articles, comparatives, etc.
            $table->unsignedBigInteger('itemable_id')->nullable();
            $table->string('title_hint', 300); // suggested title before generation
            $table->jsonb('config_override')->nullable(); // per-item config overrides
            $table->string('status', 20)->default('pending'); // pending, generating, completed, failed, skipped
            $table->text('error_message')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['campaign_id', 'status']);
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_campaign_items');
        Schema::dropIfExists('content_generation_campaigns');
        Schema::dropIfExists('generation_logs');
    }
};
