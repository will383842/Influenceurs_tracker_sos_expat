<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('country_paa_questions', function (Blueprint $table) {
            $table->id();
            $table->char('country_code', 2)->index();
            $table->string('language', 5)->default('fr')->index();
            $table->text('question');                          // Requête Google exacte
            $table->string('intent', 30)->default('informational'); // informational/transactional/commercial_investigation/urgency
            $table->string('content_type', 30)->default('qa'); // qa, article, guide, pain_point, comparative
            $table->string('source', 20)->default('google_suggest'); // google_suggest, bing_suggest, manual
            $table->integer('score')->default(0);             // Position dans la liste suggest (0 = top)
            $table->boolean('used')->default(false);          // Déjà utilisé pour générer un article
            $table->timestamps();

            $table->unique(['country_code', 'language', 'question'], 'paa_unique_question');
            $table->index(['country_code', 'language', 'used', 'intent']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('country_paa_questions');
    }
};
