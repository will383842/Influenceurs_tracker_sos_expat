<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Topic clusters — groups of source articles around a theme
        Schema::create('topic_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200);
            $table->string('slug', 200);
            $table->string('country', 100);
            $table->string('category', 50);
            $table->string('language', 5)->default('fr');
            $table->text('description')->nullable();
            $table->integer('source_articles_count')->default(0);
            $table->string('status', 20)->default('pending'); // pending, ready, generating, generated, archived
            $table->jsonb('keywords_detected')->nullable();
            $table->foreignId('generated_article_id')->nullable()->constrained('generated_articles')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['country', 'category']);
            $table->index('status');
            $table->index('generated_article_id');
        });

        // Pivot: which source articles belong to which cluster
        Schema::create('topic_cluster_articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('topic_clusters')->onDelete('cascade');
            $table->foreignId('source_article_id')->constrained('content_articles')->onDelete('cascade');
            $table->integer('relevance_score')->default(50);
            $table->boolean('is_primary')->default(false);
            $table->string('processing_status', 20)->default('pending'); // pending, extracted, used
            $table->jsonb('extracted_facts')->nullable();
            $table->timestamps();

            $table->unique(['cluster_id', 'source_article_id']);
        });

        // Research briefs from Perplexity per cluster
        Schema::create('research_briefs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('topic_clusters')->onDelete('cascade');
            $table->longText('perplexity_response')->nullable();
            $table->jsonb('extracted_facts')->nullable();
            $table->jsonb('recent_data')->nullable();
            $table->jsonb('identified_gaps')->nullable();
            $table->jsonb('paa_questions')->nullable();
            $table->jsonb('suggested_keywords')->nullable();
            $table->jsonb('suggested_structure')->nullable();
            $table->integer('tokens_used')->default(0);
            $table->integer('cost_cents')->default(0);
            $table->timestamps();

            $table->index('cluster_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('research_briefs');
        Schema::dropIfExists('topic_cluster_articles');
        Schema::dropIfExists('topic_clusters');
    }
};
