<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_clusters', function (Blueprint $table) {
            $table->id();
            $table->string('name', 300);
            $table->string('slug', 300);
            $table->string('country', 100);
            $table->string('country_slug', 100)->nullable();
            $table->string('continent', 50)->nullable();
            $table->string('category', 50)->nullable();
            $table->string('language', 5)->default('fr');
            $table->integer('total_questions')->default(0);
            $table->integer('total_views')->default(0);
            $table->integer('total_replies')->default(0);
            $table->integer('popularity_score')->default(0);
            $table->string('status', 20)->default('pending');
            $table->foreignId('generated_article_id')->nullable()->constrained('generated_articles')->nullOnDelete();
            $table->integer('generated_qa_count')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['country', 'category']);
            $table->index('status');
            $table->index(['popularity_score'], 'question_clusters_popularity_desc_index');
            $table->index('language');
        });

        Schema::create('question_cluster_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cluster_id')->constrained('question_clusters')->cascadeOnDelete();
            $table->foreignId('question_id')->constrained('content_questions')->cascadeOnDelete();
            $table->boolean('is_primary')->default(false);
            $table->decimal('similarity_score', 5, 2)->default(0);
            $table->timestamps();

            $table->unique(['cluster_id', 'question_id']);
        });

        Schema::table('content_questions', function (Blueprint $table) {
            $table->foreignId('cluster_id')->nullable()->constrained('question_clusters')->nullOnDelete();
            $table->foreignId('qa_entry_id')->nullable()->constrained('qa_entries')->nullOnDelete();
            $table->foreignId('generated_article_id')->nullable()->constrained('generated_articles')->nullOnDelete();

            $table->index('cluster_id');
            $table->index('qa_entry_id');
        });
    }

    public function down(): void
    {
        Schema::table('content_questions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cluster_id');
            $table->dropConstrainedForeignId('qa_entry_id');
            $table->dropConstrainedForeignId('generated_article_id');
        });

        Schema::dropIfExists('question_cluster_items');
        Schema::dropIfExists('question_clusters');
    }
};
