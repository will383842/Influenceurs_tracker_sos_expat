<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keyword tracking — central keyword registry
        Schema::create('keyword_tracking', function (Blueprint $table) {
            $table->id();
            $table->string('keyword', 300);
            $table->string('type', 20); // primary, secondary, long_tail, lsi, paa, semantic
            $table->string('language', 5);
            $table->string('country', 100)->nullable();
            $table->string('category', 50)->nullable();
            $table->integer('search_volume_estimate')->nullable();
            $table->integer('difficulty_estimate')->nullable();
            $table->string('trend', 20)->nullable(); // rising, stable, declining
            $table->integer('articles_using_count')->default(0);
            $table->timestamp('first_used_at')->nullable();
            $table->timestamps();

            $table->unique(['keyword', 'language']);
            $table->index('type');
            $table->index('language');
            $table->index('country');
            $table->index('category');
            $table->index('articles_using_count');
        });

        // Pivot: which keywords are used in which articles
        Schema::create('article_keywords', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('generated_articles')->onDelete('cascade');
            $table->foreignId('keyword_id')->constrained('keyword_tracking')->onDelete('cascade');
            $table->string('usage_type', 30); // h1, h2, h3, content, meta_title, meta_description, alt_text, anchor, faq_question, faq_answer
            $table->decimal('density_percent', 5, 2)->nullable();
            $table->integer('occurrences')->default(1);
            $table->string('position_context', 200)->nullable();
            $table->timestamps();

            $table->unique(['article_id', 'keyword_id']);
            $table->index('keyword_id');
            $table->index('usage_type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_keywords');
        Schema::dropIfExists('keyword_tracking');
    }
};
