<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sondages', function (Blueprint $table) {
            $table->id();
            $table->uuid('external_id')->unique(); // clé partagée avec le Blog
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
            $table->string('language', 5)->default('fr');
            $table->timestamp('closes_at')->nullable();
            $table->boolean('synced_to_blog')->default(false);
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('language');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sondages');
    }
};
