<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Translation batches — batch translation jobs
        Schema::create('translation_batches', function (Blueprint $table) {
            $table->id();
            $table->string('target_language', 5);
            $table->string('content_type', 30)->default('article'); // article, qa, all
            $table->string('status', 20)->default('pending'); // pending, running, paused, completed, cancelled, failed
            $table->integer('total_items')->default(0);
            $table->integer('completed_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->integer('skipped_items')->default(0);
            $table->integer('total_cost_cents')->default(0);
            $table->unsignedBigInteger('current_item_id')->nullable();
            $table->jsonb('error_log')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['target_language', 'content_type']);
            $table->index('status');
        });

        // SEO checklists — per-article SEO audit
        Schema::create('seo_checklists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('generated_articles')->onDelete('cascade');

            // On-Page
            $table->boolean('has_single_h1')->default(false);
            $table->boolean('h1_contains_keyword')->default(false);
            $table->integer('title_tag_length')->nullable();
            $table->boolean('title_tag_contains_keyword')->default(false);
            $table->integer('meta_desc_length')->nullable();
            $table->boolean('meta_desc_contains_cta')->default(false);
            $table->boolean('keyword_in_first_paragraph')->default(false);
            $table->boolean('keyword_density_ok')->default(false);
            $table->boolean('heading_hierarchy_valid')->default(false);
            $table->boolean('has_table_or_list')->default(false);

            // Structured Data
            $table->boolean('has_article_schema')->default(false);
            $table->boolean('has_faq_schema')->default(false);
            $table->boolean('has_breadcrumb_schema')->default(false);
            $table->boolean('has_speakable_schema')->default(false);
            $table->boolean('has_howto_schema')->default(false);
            $table->boolean('json_ld_valid')->default(false);

            // E-E-A-T
            $table->boolean('has_author_box')->default(false);
            $table->boolean('has_sources_cited')->default(false);
            $table->boolean('has_date_published')->default(false);
            $table->boolean('has_date_modified')->default(false);
            $table->boolean('has_official_links')->default(false);

            // Links
            $table->integer('internal_links_count')->default(0);
            $table->integer('external_links_count')->default(0);
            $table->integer('official_links_count')->default(0);
            $table->integer('broken_links_count')->default(0);

            // Featured Snippets
            $table->boolean('has_definition_paragraph')->default(false);
            $table->boolean('has_numbered_steps')->default(false);
            $table->boolean('has_comparison_table')->default(false);

            // AEO (Answer Engine Optimization)
            $table->boolean('has_speakable_content')->default(false);
            $table->boolean('has_direct_answers')->default(false);
            $table->integer('paa_questions_covered')->default(0);

            // Images
            $table->boolean('all_images_have_alt')->default(false);
            $table->boolean('featured_image_has_keyword')->default(false);
            $table->integer('images_count')->default(0);

            // Translation
            $table->boolean('hreflang_complete')->default(false);
            $table->integer('translations_count')->default(0);

            // Score
            $table->integer('overall_checklist_score')->default(0);

            $table->timestamps();

            $table->unique('article_id');
            $table->index('overall_checklist_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_checklists');
        Schema::dropIfExists('translation_batches');
    }
};
