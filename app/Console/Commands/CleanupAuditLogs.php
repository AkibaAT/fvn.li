<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChangeLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

class CleanupAuditLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'audit:cleanup
                           {--dry-run : Show what would be deleted without actually deleting}
                           {--force : Skip confirmation prompts}
                           {--sensitive-only : Only clean up sensitive data}
                           {--ip-only : Only clean up IP addresses}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up audit logs according to retention policies and privacy regulations';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! Config::get('audit.retention.enabled', true)) {
            $this->info('Audit log retention is disabled in config.');

            return self::SUCCESS;
        }

        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $sensitiveOnly = $this->option('sensitive-only');
        $ipOnly = $this->option('ip-only');

        $this->info('Starting audit log cleanup...');

        if ($dryRun) {
            $this->warn('DRY RUN MODE - No data will be deleted');
        }

        // Get retention periods from config
        $generalRetentionDays = Config::get('audit.retention.days', 2555);
        $sensitiveRetentionDays = Config::get('audit.retention.sensitive_data_retention_days', 90);
        $ipRetentionDays = Config::get('audit.retention.ip_address_retention_days', 365);

        $totalDeleted = 0;

        // Clean up IP addresses that have exceeded retention period
        if (! $sensitiveOnly) {
            $ipCleanupCount = $this->cleanupIpAddresses($ipRetentionDays, $dryRun, $force);
            $totalDeleted += $ipCleanupCount;
        }

        // Clean up sensitive model data
        if (! $ipOnly) {
            $sensitiveCleanupCount = $this->cleanupSensitiveData($sensitiveRetentionDays, $dryRun, $force);
            $totalDeleted += $sensitiveCleanupCount;
        }

        // Clean up general audit logs (oldest logs)
        if (! $sensitiveOnly && ! $ipOnly) {
            $generalCleanupCount = $this->cleanupGeneralLogs($generalRetentionDays, $dryRun, $force);
            $totalDeleted += $generalCleanupCount;
        }

        if ($dryRun) {
            $this->info("DRY RUN: Would delete {$totalDeleted} audit log entries/fields.");
        } else {
            $this->info("Successfully cleaned up {$totalDeleted} audit log entries/fields.");
        }

        return self::SUCCESS;
    }

    /**
     * Clean up IP addresses from audit logs after retention period
     */
    private function cleanupIpAddresses(int $retentionDays, bool $dryRun, bool $force): int
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $this->info("Cleaning up IP addresses older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d')})...");

        // Count affected records
        $affectedCount = ChangeLog::where('timestamp', '<', $cutoffDate)
            ->whereRaw("context->'ip_address' IS NOT NULL")
            ->count();

        if ($affectedCount === 0) {
            $this->info('No IP addresses found that need cleanup.');

            return 0;
        }

        if (! $force && ! $dryRun) {
            if (! $this->confirm("This will anonymize IP addresses in {$affectedCount} audit log entries. Continue?")) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        if ($dryRun) {
            $this->warn("DRY RUN: Would anonymize IP addresses in {$affectedCount} entries");

            return $affectedCount;
        }

        // Use a batch update to anonymize IP addresses
        $batchSize = 1000;
        $processed = 0;

        ChangeLog::where('timestamp', '<', $cutoffDate)
            ->whereRaw("context->'ip_address' IS NOT NULL")
            ->chunkById($batchSize, function ($logs) use (&$processed) {
                $ids = $logs->pluck('id')->toArray();

                // Update the context to remove IP addresses
                DB::table('change_logs')
                    ->whereIn('id', $ids)
                    ->update([
                        'context' => DB::raw("context - 'ip_address'"),
                        'updated_at' => now(),
                    ]);

                $processed += count($ids);
                $this->info("Processed {$processed} entries...");
            });

        $this->info("Anonymized IP addresses in {$processed} entries.");

        return $processed;
    }

    /**
     * Clean up audit logs for models marked as sensitive
     */
    private function cleanupSensitiveData(int $retentionDays, bool $dryRun, bool $force): int
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);
        $modelSettings = Config::get('audit.model_settings', []);

        $this->info("Cleaning up sensitive model data older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d')})...");

        // Find models marked as sensitive
        $sensitiveModels = [];
        foreach ($modelSettings as $modelClass => $settings) {
            if ($settings['sensitive'] ?? false) {
                $sensitiveModels[] = $modelClass;
            }
        }

        if (empty($sensitiveModels)) {
            $this->info('No sensitive models configured for cleanup.');

            return 0;
        }

        $affectedCount = ChangeLog::where('timestamp', '<', $cutoffDate)
            ->whereIn('entity_type', $sensitiveModels)
            ->count();

        if ($affectedCount === 0) {
            $this->info('No sensitive model data found that needs cleanup.');

            return 0;
        }

        if (! $force && ! $dryRun) {
            $modelList = implode(', ', array_map(fn ($model) => class_basename($model), $sensitiveModels));
            if (! $this->confirm("This will delete {$affectedCount} audit log entries for sensitive models ({$modelList}). Continue?")) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        if ($dryRun) {
            $this->warn("DRY RUN: Would delete {$affectedCount} sensitive audit log entries");

            return $affectedCount;
        }

        // Delete sensitive model audit logs
        $deleted = ChangeLog::where('timestamp', '<', $cutoffDate)
            ->whereIn('entity_type', $sensitiveModels)
            ->delete();

        $this->info("Deleted {$deleted} sensitive audit log entries.");

        return $deleted;
    }

    /**
     * Clean up general audit logs after retention period
     */
    private function cleanupGeneralLogs(int $retentionDays, bool $dryRun, bool $force): int
    {
        $cutoffDate = Carbon::now()->subDays($retentionDays);

        $this->info("Cleaning up general audit logs older than {$retentionDays} days (before {$cutoffDate->format('Y-m-d')})...");

        $affectedCount = ChangeLog::where('timestamp', '<', $cutoffDate)->count();

        if ($affectedCount === 0) {
            $this->info('No general audit logs found that need cleanup.');

            return 0;
        }

        if (! $force && ! $dryRun) {
            if (! $this->confirm("This will delete {$affectedCount} audit log entries. Continue?")) {
                $this->info('Operation cancelled.');

                return 0;
            }
        }

        if ($dryRun) {
            $this->warn("DRY RUN: Would delete {$affectedCount} general audit log entries");

            return $affectedCount;
        }

        // Delete old audit logs in batches to avoid memory issues
        $deleted = 0;
        $batchSize = 1000;

        do {
            $batchDeleted = ChangeLog::where('timestamp', '<', $cutoffDate)
                ->limit($batchSize)
                ->delete();

            $deleted += $batchDeleted;

            if ($batchDeleted > 0) {
                $this->info("Deleted batch of {$batchDeleted} entries (total: {$deleted})...");
            }
        } while ($batchDeleted > 0);

        $this->info("Deleted {$deleted} general audit log entries.");

        return $deleted;
    }
}
