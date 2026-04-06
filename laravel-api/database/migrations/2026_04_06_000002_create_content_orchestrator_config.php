<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_orchestrator_config', function (Blueprint $table) {
            $table->id();
            $table->integer('daily_target')->default(20)->comment('Total articles per day');
            $table->boolean('auto_pilot')->default(false)->comment('Auto-generate and publish');
            $table->jsonb('type_distribution')->default('{}')->comment('{"qa": 25, "news": 15, "article": 20, ...} percentages');
            $table->jsonb('priority_countries')->default('[]')->comment('["FR","US","GB",...] ordered');
            $table->string('status')->default('paused')->comment('running, paused, stopped');
            $table->timestamp('last_run_at')->nullable();
            $table->integer('today_generated')->default(0);
            $table->integer('today_cost_cents')->default(0);
            $table->timestamps();
        });

        // Insert default config
        \Illuminate\Support\Facades\DB::table('content_orchestrator_config')->insert([
            'daily_target' => 20,
            'auto_pilot' => false,
            'type_distribution' => json_encode([
                'qa' => 25,
                'news' => 15,
                'article' => 20,
                'guide' => 10,
                'guide_city' => 10,
                'comparative' => 10,
                'outreach' => 5,
                'testimonial' => 5,
            ]),
            'priority_countries' => json_encode(['FR','US','GB','ES','DE','TH','PT','CA','AU','IT','AE','JP','SG','MA']),
            'status' => 'paused',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('content_orchestrator_config');
    }
};
