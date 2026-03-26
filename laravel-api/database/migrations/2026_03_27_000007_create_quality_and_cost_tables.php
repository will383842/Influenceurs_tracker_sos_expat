<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // API cost tracking per call
        Schema::create('api_costs', function (Blueprint $table) {
            $table->id();
            $table->string('service', 50); // openai, perplexity, dalle, unsplash, anthropic
            $table->string('model', 50);
            $table->string('operation', 50); // article_generation, translation, research, image, email
            $table->integer('input_tokens')->default(0);
            $table->integer('output_tokens')->default(0);
            $table->integer('cost_cents')->default(0); // in USD cents
            $table->string('costable_type')->nullable();
            $table->unsignedBigInteger('costable_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('service');
            $table->index('model');
            $table->index('operation');
            $table->index('created_at');
            $table->index(['costable_type', 'costable_id']);
        });

        // Brand voice guidelines
        Schema::create('brand_guidelines', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->jsonb('rules'); // [{type: 'forbidden_words'|'required_tone'|'style', value: ...}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Golden examples for quality reference
        Schema::create('golden_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained('generated_articles')->onDelete('cascade');
            $table->jsonb('criteria')->nullable(); // {why_good: "...", key_elements: [...]}
            $table->integer('score')->default(100);
            $table->timestamps();

            $table->index('article_id');
        });

        // Prompt templates for AI generation phases
        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('content_type', 50); // article, comparative, landing, press_release, translation, faq
            $table->string('phase', 50); // research, title, excerpt, content, faq, meta, etc.
            $table->longText('system_message');
            $table->longText('user_message_template'); // with {{variable}} placeholders
            $table->string('model', 50)->default('gpt-4o');
            $table->decimal('temperature', 3, 2)->default(0.7);
            $table->integer('max_tokens')->default(4000);
            $table->boolean('is_active')->default(true);
            $table->integer('version')->default(1);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['content_type', 'phase', 'is_active']);
            $table->index('version');
        });

        // Reusable HTML template blocks
        Schema::create('content_template_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('content_type', 50); // article, comparative, landing
            $table->string('language', 5);
            $table->longText('html_template'); // HTML with {{variable}} placeholders
            $table->jsonb('variables')->nullable(); // [{name, type, default}]
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['content_type', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_template_blocks');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('golden_examples');
        Schema::dropIfExists('brand_guidelines');
        Schema::dropIfExists('api_costs');
    }
};
