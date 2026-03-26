<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Publishing endpoints (Firestore, WordPress, webhook, export)
        Schema::create('publishing_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('type', 50); // firestore, wordpress, webhook, export
            $table->jsonb('config'); // {project_id, collection, url, auth_headers, etc.}
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('type');
            $table->index('is_active');
        });

        // Publication queue (polymorphic)
        Schema::create('publication_queue', function (Blueprint $table) {
            $table->id();
            $table->string('publishable_type');
            $table->unsignedBigInteger('publishable_id');
            $table->foreignId('endpoint_id')->constrained('publishing_endpoints')->onDelete('cascade');
            $table->string('status', 20)->default('pending'); // pending, publishing, published, failed, cancelled
            $table->string('priority', 10)->default('default'); // high, default, low
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->integer('attempts')->default(0);
            $table->integer('max_attempts')->default(5);
            $table->text('last_error')->nullable();
            $table->string('external_id', 200)->nullable(); // ID on the target platform
            $table->string('external_url', 1000)->nullable(); // URL on the target platform
            $table->timestamps();

            $table->index(['publishable_type', 'publishable_id']);
            $table->index('status');
            $table->index('priority');
            $table->index('scheduled_at');
            $table->index('endpoint_id');
        });

        // Rate limiting / scheduling config per endpoint
        Schema::create('publication_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('endpoint_id')->constrained('publishing_endpoints')->onDelete('cascade');
            $table->integer('max_per_day')->default(100);
            $table->integer('max_per_hour')->default(15);
            $table->integer('min_interval_minutes')->default(6);
            $table->time('active_hours_start')->default('09:00');
            $table->time('active_hours_end')->default('17:00');
            $table->jsonb('active_days')->default('["mon","tue","wed","thu","fri"]');
            $table->integer('auto_pause_on_errors')->default(5);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('endpoint_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_schedules');
        Schema::dropIfExists('publication_queue');
        Schema::dropIfExists('publishing_endpoints');
    }
};
