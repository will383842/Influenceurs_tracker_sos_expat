<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds a campaign_distribution_mode flag to content_orchestrator_config.
 *
 * Two modes:
 *   'fixed_plan' (default, current behavior): the orchestrator uses the figé
 *      plan from CountryCampaignCommand::getContentPlan() — 262 hand-curated
 *      topics with predetermined per-type quantities.
 *
 *   'percentage' (new): the orchestrator recalculates per-type quotas from the
 *      type_distribution column (set via the dashboard "Configuration" page),
 *      multiplying each percentage by campaign_articles_per_country (262).
 *      The plan topics are still used as the topic pool, but the per-type
 *      ceiling comes from the % distribution.
 *
 * Default 'fixed_plan' = no behavior change for existing deployments.
 * Switch to 'percentage' via SQL when ready:
 *   UPDATE content_orchestrator_config SET campaign_distribution_mode = 'percentage';
 * Switch back instantly with the inverse — no code redeploy needed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_orchestrator_config', function (Blueprint $table) {
            $table->string('campaign_distribution_mode', 20)
                ->default('fixed_plan')
                ->after('campaign_articles_per_country');
        });
    }

    public function down(): void
    {
        Schema::table('content_orchestrator_config', function (Blueprint $table) {
            $table->dropColumn('campaign_distribution_mode');
        });
    }
};
