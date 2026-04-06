<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_orchestrator_logs', function (Blueprint $table) {
            $table->id();
            $table->date('log_date');
            $table->string('content_type', 50);
            $table->unsignedInteger('generated')->default(0);
            $table->unsignedInteger('published')->default(0);
            $table->unsignedInteger('translated')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->unsignedInteger('duplicates_blocked')->default(0);
            $table->decimal('avg_seo_score', 5, 2)->default(0);
            $table->decimal('avg_aeo_score', 5, 2)->default(0);
            $table->unsignedInteger('cost_cents')->default(0);
            $table->timestamps();

            $table->unique(['log_date', 'content_type']);
            $table->index('log_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_orchestrator_logs');
    }
};
