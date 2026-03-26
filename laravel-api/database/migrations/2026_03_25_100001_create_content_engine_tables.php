<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Sources (expat.com, etc.)
        Schema::create('content_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->string('base_url', 500)->unique();
            $table->unsignedInteger('total_countries')->default(0);
            $table->unsignedInteger('total_articles')->default(0);
            $table->unsignedInteger('total_links')->default(0);
            $table->string('status', 20)->default('pending'); // pending, scraping, paused, completed
            $table->timestamp('last_scraped_at')->nullable();
            $table->timestamps();
        });

        // Countries per source
        Schema::create('content_countries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('content_sources')->onDelete('cascade');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->string('continent', 50)->nullable();
            $table->string('guide_url', 500);
            $table->unsignedInteger('articles_count')->default(0);
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->unique(['source_id', 'slug']);
            $table->index('continent');
        });

        // Scraped articles
        Schema::create('content_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('content_sources')->onDelete('cascade');
            $table->foreignId('country_id')->nullable()->constrained('content_countries')->onDelete('cascade');
            $table->string('title', 500);
            $table->string('slug', 500);
            $table->string('url', 1000);
            $table->string('url_hash', 64)->unique(); // SHA-256 of url for safe unique index
            $table->string('category', 50)->nullable();
            $table->longText('content_text')->nullable();
            $table->longText('content_html')->nullable();
            $table->unsignedInteger('word_count')->default(0);
            $table->string('language', 10)->default('fr');
            $table->json('external_links')->nullable();
            $table->json('ads_and_sponsors')->nullable();
            $table->json('images')->nullable();
            $table->string('meta_title', 500)->nullable();
            $table->string('meta_description', 1000)->nullable();
            $table->boolean('is_guide')->default(false);
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->index(['source_id', 'country_id']);
            $table->index('country_id');
            $table->index('category');
            $table->index('is_guide');
            $table->index('language');
        });

        // External links (deduplicated)
        Schema::create('content_external_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained('content_sources')->onDelete('cascade');
            $table->foreignId('article_id')->constrained('content_articles')->onDelete('cascade');
            $table->string('url', 1000);
            $table->string('url_hash', 64); // SHA-256 for dedup index
            $table->string('original_url', 1000);
            $table->string('domain', 255);
            $table->string('anchor_text', 500)->nullable();
            $table->text('context')->nullable();
            $table->foreignId('country_id')->nullable()->constrained('content_countries')->nullOnDelete();
            $table->string('link_type', 20)->default('other');
            $table->boolean('is_affiliate')->default(false);
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamps();

            $table->index('article_id');
            $table->index('country_id');
            $table->index('domain');
            $table->index('link_type');
            $table->index('is_affiliate');
            $table->index(['source_id', 'domain']);
            $table->unique(['source_id', 'url_hash']); // For deduplication
        });

        // Generated articles (phase 2)
        Schema::create('content_generated', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_article_id')->nullable()->constrained('content_articles')->nullOnDelete();
            $table->foreignId('country_id')->nullable()->constrained('content_countries')->nullOnDelete();
            $table->string('title', 500);
            $table->string('slug', 500);
            $table->longText('content_text')->nullable();
            $table->longText('content_html')->nullable();
            $table->json('external_links_used')->nullable();
            $table->string('ai_model', 50)->nullable();
            $table->unsignedInteger('ai_tokens_used')->default(0);
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('published_url', 1000)->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('country_id');
            $table->index('source_article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_generated');
        Schema::dropIfExists('content_external_links');
        Schema::dropIfExists('content_articles');
        Schema::dropIfExists('content_countries');
        Schema::dropIfExists('content_sources');
    }
};
