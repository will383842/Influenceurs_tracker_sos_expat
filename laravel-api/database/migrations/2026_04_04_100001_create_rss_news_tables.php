<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_feeds', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 255);
            $table->string('url', 500)->unique();
            $table->string('language', 5)->default('fr');
            $table->string('country', 5)->nullable();
            $table->string('category', 100)->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedTinyInteger('fetch_interval_hours')->default(4);
            $table->timestamp('last_fetched_at')->nullable();
            $table->unsignedInteger('items_fetched_count')->default(0);
            $table->unsignedTinyInteger('relevance_threshold')->default(65);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('rss_feed_items', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('feed_id')->constrained('rss_feeds')->cascadeOnDelete();
            $table->string('guid', 500);
            $table->string('title', 500);
            $table->string('url', 500);
            $table->string('source_name', 255)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('original_title', 500)->nullable();
            $table->text('original_excerpt')->nullable();
            $table->longText('original_content')->nullable();
            $table->string('language', 5)->default('fr');
            $table->string('country', 5)->nullable();
            $table->unsignedTinyInteger('relevance_score')->nullable();
            $table->string('relevance_category', 100)->nullable();
            $table->string('relevance_reason', 500)->nullable();
            $table->string('status', 50)->default('pending');
            $table->unsignedTinyInteger('similarity_score')->nullable();
            $table->string('blog_article_uuid', 255)->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['feed_id', 'guid']);
            $table->index('status');
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_feed_items');
        Schema::dropIfExists('rss_feeds');
    }
};
