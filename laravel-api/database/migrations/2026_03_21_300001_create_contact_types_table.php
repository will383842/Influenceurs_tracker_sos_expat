<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dynamic contact types — managed from admin console.
 * Replaces the hardcoded PHP Enum for type definitions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_types', function (Blueprint $table) {
            $table->id();
            $table->string('value', 50)->unique();   // slug: influenceur, school, erasmus...
            $table->string('label', 100);              // Display: "Influenceurs", "Écoles Erasmus"
            $table->string('icon', 10)->default('📌'); // Emoji
            $table->string('color', 7)->default('#6B7280'); // Hex color
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_types');
    }
};
