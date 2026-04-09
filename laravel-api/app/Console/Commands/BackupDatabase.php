<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Daily PostgreSQL backup command.
 * Stores compressed dumps in storage/backups/ (30 days retention).
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database';
    protected $description = 'Backup PostgreSQL database to storage/backups/';

    public function handle(): int
    {
        $backupDir = storage_path('backups');
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0775, true);
        }

        $date = now()->format('Y-m-d_Hi');
        $filename = "db_{$date}.sql.gz";
        $filepath = "{$backupDir}/{$filename}";

        $host = config('database.connections.pgsql.host');
        $port = config('database.connections.pgsql.port', '5432');
        $database = config('database.connections.pgsql.database');
        $username = config('database.connections.pgsql.username');
        $password = config('database.connections.pgsql.password');

        $this->info("[Backup] Starting PostgreSQL dump...");

        // Use pg_dump with gzip compression
        $cmd = sprintf(
            'PGPASSWORD=%s pg_dump -h %s -p %s -U %s %s | gzip > %s 2>&1',
            escapeshellarg($password),
            escapeshellarg($host),
            escapeshellarg($port),
            escapeshellarg($username),
            escapeshellarg($database),
            escapeshellarg($filepath)
        );

        exec($cmd, $output, $exitCode);

        if ($exitCode !== 0 || !file_exists($filepath) || filesize($filepath) < 100) {
            $this->error("[Backup] FAILED: exit code {$exitCode}");
            Log::error('Database backup failed', ['exit_code' => $exitCode, 'output' => $output]);
            // Clean up empty/broken file
            if (file_exists($filepath)) unlink($filepath);
            return self::FAILURE;
        }

        $size = $this->humanSize(filesize($filepath));
        $this->info("[Backup] Dump saved: {$filename} ({$size})");

        // Record counts for verification
        try {
            $counts = [
                'influenceurs'   => DB::table('influenceurs')->whereNull('deleted_at')->count(),
                'contacts'       => DB::table('contacts')->whereNull('deleted_at')->count(),
                'users'          => DB::table('users')->whereNull('deleted_at')->count(),
                'ai_sessions'    => DB::table('ai_research_sessions')->count(),
                'templates'      => DB::table('email_templates')->count(),
                'contact_types'  => DB::table('contact_types')->count(),
                'content_metrics' => DB::table('content_metrics')->count(),
            ];
            $this->info("[Backup] Records: " . json_encode($counts));

            // Save manifest
            file_put_contents("{$backupDir}/manifest_{$date}.json", json_encode([
                'date'     => $date,
                'filename' => $filename,
                'size'     => $size,
                'records'  => $counts,
            ], JSON_PRETTY_PRINT));
        } catch (\Throwable $e) {
            $this->warn("[Backup] Could not count records: " . $e->getMessage());
        }

        // Off-site backup to Hetzner Storage Box
        $storageBoxUser = env('STORAGE_BOX_USER');
        $storageBoxHost = env('STORAGE_BOX_HOST');
        $storageBoxPath = env('STORAGE_BOX_PATH', '/backups/mission-control');

        if ($storageBoxUser && $storageBoxHost) {
            $this->info("[Backup] Syncing off-site to Hetzner Storage Box...");
            $rsyncCmd = sprintf(
                'rsync -az --timeout=120 %s/ %s@%s:%s/ 2>&1',
                escapeshellarg($backupDir),
                escapeshellarg($storageBoxUser),
                escapeshellarg($storageBoxHost),
                escapeshellarg($storageBoxPath)
            );
            exec($rsyncCmd, $rsyncOutput, $rsyncExit);
            if ($rsyncExit === 0) {
                $this->info("[Backup] Off-site sync complete");
            } else {
                $this->warn("[Backup] Off-site sync failed (exit {$rsyncExit})");
                Log::warning('Backup off-site sync failed', ['exit_code' => $rsyncExit, 'output' => $rsyncOutput]);
            }
        }

        // Cleanup: keep last 30 days
        $this->cleanup($backupDir, 30);

        $backupCount = count(glob("{$backupDir}/db_*.sql.gz"));
        $this->info("[Backup] Complete: {$backupCount} backups stored");

        Log::info('Database backup completed', [
            'filename' => $filename,
            'size'     => $size,
        ]);

        return self::SUCCESS;
    }

    private function cleanup(string $dir, int $keepDays): void
    {
        $cutoff = now()->subDays($keepDays)->timestamp;

        foreach (glob("{$dir}/db_*.sql.gz") as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
        foreach (glob("{$dir}/manifest_*.json") as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
            }
        }
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
