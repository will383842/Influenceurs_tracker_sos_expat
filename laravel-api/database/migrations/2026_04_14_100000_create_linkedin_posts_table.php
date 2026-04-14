<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_posts', function (Blueprint $table) {
            $table->id();

            // Source content
            $table->enum('source_type', ['article', 'faq', 'testimonial', 'news', 'case_study', 'tip']);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('source_title')->nullable();

            // LinkedIn content
            $table->enum('day_type', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday']);
            $table->enum('lang', ['fr', 'en', 'both'])->default('fr');
            $table->enum('account', ['page', 'personal', 'both'])->default('both');
            $table->text('hook');
            $table->text('body');
            $table->json('hashtags')->nullable();

            // Scheduling
            $table->enum('status', ['draft', 'scheduled', 'published', 'failed'])->default('draft');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();

            // LinkedIn API
            $table->string('li_post_id_page')->nullable();
            $table->string('li_post_id_personal')->nullable();

            // Analytics (updated by sync job)
            $table->unsignedInteger('reach')->default(0);
            $table->unsignedInteger('likes')->default(0);
            $table->unsignedInteger('comments')->default(0);
            $table->unsignedInteger('shares')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->decimal('engagement_rate', 5, 2)->default(0);

            // Phase tracking
            $table->unsignedTinyInteger('phase')->default(1); // 1 = FR clients, 2 = global

            $table->string('error_message')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index('day_type');
            $table->index('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_posts');
    }
};
