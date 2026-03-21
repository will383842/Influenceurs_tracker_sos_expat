<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * FUSION: Email templates for outreach (from Mission Control's 17 templates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('contact_type', 50);
            $table->string('language', 10)->default('fr');
            $table->string('name', 255);
            $table->string('subject', 500);
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('step')->default(1);
            $table->unsignedSmallInteger('delay_days')->default(0);
            $table->timestamps();

            $table->index(['contact_type', 'language', 'is_active', 'step'], 'idx_tpl_type_lang_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
