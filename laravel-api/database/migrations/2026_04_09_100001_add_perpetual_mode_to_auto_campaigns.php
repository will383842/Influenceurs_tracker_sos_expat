<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auto_campaigns', function (Blueprint $table) {
            $table->boolean('auto_restart')->default(false)->after('queue_position');
            $table->unsignedInteger('restart_delay_hours')->default(24)->after('auto_restart');
            $table->unsignedInteger('cycles_completed')->default(0)->after('restart_delay_hours');
        });
    }

    public function down(): void
    {
        Schema::table('auto_campaigns', function (Blueprint $table) {
            $table->dropColumn(['auto_restart', 'restart_delay_hours', 'cycles_completed']);
        });
    }
};
