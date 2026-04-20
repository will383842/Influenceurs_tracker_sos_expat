<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajoute un index sur backlink_synced_at aux 2 tables qui n'en avaient pas,
 * pour aligner sur le pattern des 3 autres tables (migration 2026_04_20_000001).
 *
 * Postgres : CREATE INDEX CONCURRENTLY (pas de lock writes sur prod).
 * Autres drivers (SQLite dev, MySQL) : Schema::index() classique.
 *
 * Idempotent : IF NOT EXISTS + check hasIndex() via Schema::getIndexes().
 */
return new class extends Migration
{
    // CREATE INDEX CONCURRENTLY ne peut pas tourner dans une transaction.
    public $withinTransaction = false;

    public function up(): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (['influenceurs', 'press_contacts'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            if (! Schema::hasColumn($table, 'backlink_synced_at')) {
                continue;
            }

            $indexName = "{$table}_backlink_synced_at_index";

            if ($driver === 'pgsql') {
                DB::statement("CREATE INDEX CONCURRENTLY IF NOT EXISTS {$indexName} ON {$table} (backlink_synced_at)");
            } else {
                $existing = collect(Schema::getIndexes($table))->pluck('name')->all();
                if (! in_array($indexName, $existing, true)) {
                    Schema::table($table, function (Blueprint $t) use ($indexName) {
                        $t->index('backlink_synced_at', $indexName);
                    });
                }
            }
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        foreach (['influenceurs', 'press_contacts'] as $table) {
            $indexName = "{$table}_backlink_synced_at_index";

            if ($driver === 'pgsql') {
                DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$indexName}");
            } else {
                if (! Schema::hasTable($table)) {
                    continue;
                }
                $existing = collect(Schema::getIndexes($table))->pluck('name')->all();
                if (in_array($indexName, $existing, true)) {
                    Schema::table($table, function (Blueprint $t) use ($indexName) {
                        $t->dropIndex($indexName);
                    });
                }
            }
        }
    }
};
