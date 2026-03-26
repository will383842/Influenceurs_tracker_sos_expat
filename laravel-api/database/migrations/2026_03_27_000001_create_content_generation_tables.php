<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Generation presets (created first because generated_articles references it)
        Schema::create('generation_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->jsonb('config'); // {model, tone, length, faq_count, languages, image_source, etc.}
            $table->string('content_type', 50); // article, comparative, landing, press_release
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('content_type');
        });

        // Main generated articles
        Schema::create('generated_articles', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 300);
            $table->string('slug', 300);
            $table->string('language', 5); // fr, en, de, es, pt, ru, zh, ar, hi
            $table->string('country', 100)->nullable();
            $table->string('content_type', 50)->default('article'); // article, guide, news, tutorial
            $table->text('excerpt')->nullable();
            $table->longText('content_html')->nullable();
            $table->longText('content_text')->nullable();
            $table->string('featured_image_url', 1000)->nullable();
            $table->string('featured_image_alt', 300)->nullable();
            $table->string('featured_image_attribution', 300)->nullable();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->string('canonical_url', 1000)->nullable();
            $table->jsonb('json_ld')->nullable();
            $table->jsonb('hreflang_map')->nullable(); // {"fr": "/fr/slug", "en": "/en/slug"}
            $table->string('keywords_primary', 200)->nullable();
            $table->jsonb('keywords_secondary')->nullable(); // ["keyword1", "keyword2"]
            $table->jsonb('keyword_density')->nullable(); // {"keyword": 1.4, "other": 0.8}
            $table->integer('word_count')->default(0);
            $table->integer('reading_time_minutes')->default(0);
            $table->integer('seo_score')->default(0); // 0-100
            $table->integer('quality_score')->default(0); // 0-100
            $table->decimal('readability_score', 5, 2)->nullable(); // Flesch-Kincaid
            $table->string('status', 20)->default('draft'); // draft, generating, review, scheduled, published, archived
            $table->string('generation_model', 50)->nullable(); // gpt-4o, etc.
            $table->integer('generation_cost_cents')->default(0);
            $table->integer('generation_tokens_input')->default(0);
            $table->integer('generation_tokens_output')->default(0);
            $table->integer('generation_duration_seconds')->default(0);
            $table->foreignId('generation_preset_id')->nullable()->constrained('generation_presets')->nullOnDelete();
            $table->foreignId('source_article_id')->nullable()->constrained('content_articles')->nullOnDelete();
            $table->foreignId('parent_article_id')->nullable()->constrained('generated_articles')->nullOnDelete();
            $table->foreignId('pillar_article_id')->nullable()->constrained('generated_articles')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'language']);
            $table->index('status');
            $table->index('language');
            $table->index('country');
            $table->index('content_type');
            $table->index('seo_score');
            $table->index('quality_score');
            $table->index('published_at');
            $table->index('parent_article_id');
            $table->index('pillar_article_id');
            $table->index('created_by');
        });

        // FAQ entries per article
        Schema::create('generated_article_faqs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('generated_articles')->onDelete('cascade');
            $table->text('question');
            $table->text('answer');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['article_id', 'sort_order']);
        });

        // Sources referenced by articles
        Schema::create('generated_article_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('generated_articles')->onDelete('cascade');
            $table->string('url', 1000);
            $table->string('title', 300)->nullable();
            $table->text('excerpt')->nullable();
            $table->string('domain', 200)->nullable();
            $table->integer('trust_score')->default(50); // 0-100
            $table->timestamps();

            $table->index('article_id');
        });

        // Version history per article
        Schema::create('generated_article_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('generated_articles')->onDelete('cascade');
            $table->integer('version_number');
            $table->longText('content_html');
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->string('changes_summary', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['article_id', 'version_number']);
        });

        // Images attached to articles
        Schema::create('generated_article_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('generated_articles')->onDelete('cascade');
            $table->string('url', 1000);
            $table->string('alt_text', 300)->nullable();
            $table->string('source', 50)->default('unsplash'); // unsplash, dalle, upload, external
            $table->string('attribution', 500)->nullable();
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('article_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_article_images');
        Schema::dropIfExists('generated_article_versions');
        Schema::dropIfExists('generated_article_sources');
        Schema::dropIfExists('generated_article_faqs');
        Schema::dropIfExists('generated_articles');
        Schema::dropIfExists('generation_presets');
    }
};
