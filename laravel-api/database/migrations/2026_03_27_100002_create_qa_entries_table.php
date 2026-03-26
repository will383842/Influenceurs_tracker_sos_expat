<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_entries', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('parent_article_id')->nullable()->constrained('generated_articles')->nullOnDelete();
            $table->foreignId('cluster_id')->nullable()->constrained('topic_clusters')->nullOnDelete();
            $table->text('question');
            $table->text('answer_short'); // 40-60 words
            $table->longText('answer_detailed_html')->nullable();
            $table->string('language', 5)->default('fr');
            $table->string('country', 100)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('slug', 300);
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->string('canonical_url', 1000)->nullable();
            $table->jsonb('json_ld')->nullable();
            $table->jsonb('hreflang_map')->nullable();
            $table->string('keywords_primary', 200)->nullable();
            $table->jsonb('keywords_secondary')->nullable();
            $table->integer('seo_score')->default(0);
            $table->integer('word_count')->default(0);
            $table->string('source_type', 30)->default('article_faq'); // article_faq, paa, scraped, manual, ai_suggested
            $table->string('status', 20)->default('draft');
            $table->integer('generation_cost_cents')->default(0);
            $table->foreignId('parent_qa_id')->nullable()->constrained('qa_entries')->nullOnDelete();
            $table->jsonb('related_qa_ids')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'language']);
            $table->index('parent_article_id');
            $table->index(['country', 'category']);
            $table->index('status');
            $table->index('source_type');
            $table->index('language');
            $table->index('parent_qa_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_entries');
    }
};
