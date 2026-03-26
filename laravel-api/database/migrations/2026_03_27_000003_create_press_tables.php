<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Press releases
        Schema::create('press_releases', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('title', 300);
            $table->string('slug', 300);
            $table->string('language', 5);
            $table->longText('content_html')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('meta_title', 70)->nullable();
            $table->string('meta_description', 170)->nullable();
            $table->jsonb('json_ld')->nullable();
            $table->jsonb('hreflang_map')->nullable();
            $table->integer('seo_score')->default(0);
            $table->string('status', 20)->default('draft');
            $table->integer('generation_cost_cents')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('press_releases')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'language']);
            $table->index('status');
        });

        // Press dossiers (collections)
        Schema::create('press_dossiers', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 300);
            $table->string('slug', 300);
            $table->string('language', 5);
            $table->text('description')->nullable();
            $table->string('cover_image_url', 1000)->nullable();
            $table->string('status', 20)->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['slug', 'language']);
            $table->index('status');
        });

        // Polymorphic items within a dossier
        Schema::create('press_dossier_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dossier_id')->constrained('press_dossiers')->onDelete('cascade');
            $table->string('itemable_type');
            $table->unsignedBigInteger('itemable_id');
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['dossier_id', 'sort_order']);
            $table->index(['itemable_type', 'itemable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('press_dossier_items');
        Schema::dropIfExists('press_dossiers');
        Schema::dropIfExists('press_releases');
    }
};
