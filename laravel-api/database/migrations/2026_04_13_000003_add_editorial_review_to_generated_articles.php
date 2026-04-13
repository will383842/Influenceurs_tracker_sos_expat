<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            // LLM editorial judge overall score 0-100 (separate from the
            // mechanical quality_score which covers SEO/readability/length).
            // This one grades title intent, CTR hooks, meta_description
            // compellingness, AI-pattern detection and factual plausibility.
            $table->integer('editorial_score')->nullable()->after('fact_check_score');

            // Full judge report stored as JSON for later inspection:
            // { title_score, meta_score, content_score, fact_score, overall,
            //   issues: [...], suggestions: [...], judged_at, judge_model }
            $table->json('editorial_review')->nullable()->after('editorial_score');
        });
    }

    public function down(): void
    {
        Schema::table('generated_articles', function (Blueprint $table) {
            $table->dropColumn(['editorial_score', 'editorial_review']);
        });
    }
};
