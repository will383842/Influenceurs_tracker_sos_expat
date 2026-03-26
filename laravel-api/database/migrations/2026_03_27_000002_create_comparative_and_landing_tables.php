<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Comparative content (vs pages)
        Schema::create('comparatives', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 300);
            $table->string('slug', 300);
            $table->string('language', 5);
            $table->string('country', 100)->nullable();
            $table->jsonb('entities'); // [{name, description, pros, cons, rating}]
            $table->jsonb('comparison_data')->nullable(); // structured comparison table data
            $table->longText('content_html')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->jsonb('json_ld')->nullable();
            $table->jsonb('hreflang_map')->nullable();
            $table->integer('seo_score')->default(0);
            $table->integer('quality_score')->default(0);
            $table->string('status', 20)->default('draft');
            $table->integer('generation_cost_cents')->default(0);
            $table->integer('generation_tokens_input')->default(0);
            $table->integer('generation_tokens_output')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('comparatives')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'language']);
            $table->index('status');
            $table->index('language');
        });

        // Landing pages
        Schema::create('landing_pages', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 300);
            $table->string('slug', 300);
            $table->string('language', 5);
            $table->string('country', 100)->nullable();
            $table->jsonb('sections'); // [{type: 'hero'|'features'|'testimonials'|'cta'|'faq', content: {...}}]
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->jsonb('json_ld')->nullable();
            $table->jsonb('hreflang_map')->nullable();
            $table->integer('seo_score')->default(0);
            $table->string('status', 20)->default('draft');
            $table->integer('generation_cost_cents')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('landing_pages')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'language']);
            $table->index('status');
        });

        // CTA links for landing pages
        Schema::create('landing_cta_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('landing_page_id')->constrained('landing_pages')->onDelete('cascade');
            $table->string('url', 1000);
            $table->string('text', 200);
            $table->string('position', 50); // hero, middle, footer
            $table->string('style', 50)->default('primary'); // primary, secondary, outline
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('landing_page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('landing_cta_links');
        Schema::dropIfExists('landing_pages');
        Schema::dropIfExists('comparatives');
    }
};
