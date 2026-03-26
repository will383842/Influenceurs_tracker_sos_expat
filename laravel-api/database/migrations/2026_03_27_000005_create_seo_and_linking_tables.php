<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Internal links between content pieces (polymorphic source & target)
        Schema::create('internal_links', function (Blueprint $table) {
            $table->id();
            $table->string('source_type');
            $table->unsignedBigInteger('source_id');
            $table->string('target_type');
            $table->unsignedBigInteger('target_id');
            $table->string('anchor_text', 300);
            $table->text('context_sentence')->nullable(); // surrounding text for context
            $table->boolean('is_auto_generated')->default(true);
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['target_type', 'target_id']);
        });

        // Registry of external links used in content
        Schema::create('external_link_registry', function (Blueprint $table) {
            $table->id();
            $table->string('article_type');
            $table->unsignedBigInteger('article_id');
            $table->string('url', 1000);
            $table->string('domain', 200);
            $table->string('anchor_text', 300)->nullable();
            $table->integer('trust_score')->default(50);
            $table->boolean('is_nofollow')->default(false);
            $table->timestamps();

            $table->index(['article_type', 'article_id']);
            $table->index('domain');
        });

        // Affiliate links embedded in content
        Schema::create('affiliate_links', function (Blueprint $table) {
            $table->id();
            $table->string('article_type');
            $table->unsignedBigInteger('article_id');
            $table->string('url', 1000);
            $table->string('anchor_text', 300)->nullable();
            $table->string('program', 100)->nullable(); // sos-expat, amazon, etc.
            $table->string('position', 50)->nullable(); // inline, sidebar, cta
            $table->timestamps();

            $table->index(['article_type', 'article_id']);
            $table->index('program');
        });

        // SEO analysis results (polymorphic)
        Schema::create('seo_analyses', function (Blueprint $table) {
            $table->id();
            $table->string('analyzable_type');
            $table->unsignedBigInteger('analyzable_id');
            $table->integer('overall_score')->default(0);
            $table->integer('title_score')->default(0);
            $table->integer('meta_description_score')->default(0);
            $table->integer('headings_score')->default(0);
            $table->integer('content_score')->default(0);
            $table->integer('images_score')->default(0);
            $table->integer('internal_links_score')->default(0);
            $table->integer('external_links_score')->default(0);
            $table->integer('structured_data_score')->default(0);
            $table->integer('hreflang_score')->default(0);
            $table->integer('technical_score')->default(0);
            $table->jsonb('issues')->nullable(); // [{type, severity, message, suggestion}]
            $table->timestamp('analyzed_at');
            $table->timestamps();

            $table->index(['analyzable_type', 'analyzable_id']);
            $table->index('overall_score');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_analyses');
        Schema::dropIfExists('affiliate_links');
        Schema::dropIfExists('external_link_registry');
        Schema::dropIfExists('internal_links');
    }
};
