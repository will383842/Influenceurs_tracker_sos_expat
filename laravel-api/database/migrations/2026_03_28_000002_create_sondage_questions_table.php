<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sondage_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sondage_id')->constrained('sondages')->cascadeOnDelete();
            $table->string('text');
            $table->enum('type', ['single', 'multiple', 'open', 'scale'])->default('single');
            $table->json('options')->nullable(); // pour single/multiple
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['sondage_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sondage_questions');
    }
};
