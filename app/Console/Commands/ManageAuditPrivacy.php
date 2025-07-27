<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ChangeLog;
use App\Models\ClickStat;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class ManageAuditPrivacy extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'audit:privacy
                           {action : Action to perform: export, delete, anonymize, report}
                           {--user-id= : User ID for export/delete/anonymize actions}
                           {--email= : User email for export/delete/anonymize actions}
                           {--output= : Output file for export (defaults to storage/exports/)}
                           {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     */
    protected $description = 'Manage audit log privacy for GDPR/CCPA compliance (data export, deletion, anonymization)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = $this->argument('action');

        return match ($action) {
            'export' => $this->handleExport(),
            'delete' => $this->handleDelete(),
            'anonymize' => $this->handleAnonymize(),
            'report' => $this->handleReport(),
            default => $this->handleInvalidAction($action),
        };
    }

    /**
     * Handle data export (GDPR Article 20 - Data Portability)
     */
    private function handleExport(): int
    {
        $user = $this->getTargetUser();
        if (! $user) {
            return self::FAILURE;
        }

        $this->info("Exporting audit data and click statistics for user: {$user->name} (ID: {$user->id})");

        $exportData = ChangeLog::exportUserData($user->id);
        $clickStatsData = ClickStat::exportUserOwnedGameStats($user->id);

        if ($exportData['total_entries'] === 0 && empty($clickStatsData)) {
            $this->info('No audit logs or click statistics found for this user.');

            return self::SUCCESS;
        }

        // Combine audit logs and click statistics
        $combinedExportData = array_merge($exportData, [
            'click_statistics' => $clickStatsData,
        ]);

        // Determine output file
        $outputPath = $this->option('output');
        if (! $outputPath) {
            $timestamp = now()->format('Y-m-d_H-i-s');
            $outputPath = "exports/user_data_user_{$user->id}_{$timestamp}.json";
        }

        // Ensure exports directory exists
        Storage::makeDirectory(dirname($outputPath));

        // Write export data
        Storage::put($outputPath, json_encode($combinedExportData, JSON_PRETTY_PRINT));

        $auditEntries = $exportData['total_entries'] ?? 0;
        $clickEntries = $clickStatsData['total_entries'] ?? 0;
        $totalEntries = $auditEntries + $clickEntries;

        $this->info("Exported {$totalEntries} total entries ({$auditEntries} audit logs, {$clickEntries} click stats) to: " . Storage::path($outputPath));

        $this->table(['Field', 'Value'], [
            ['User ID', $exportData['user_id'] ?? $user->id],
            ['Exported At', $exportData['exported_at'] ?? now()->toISOString()],
            ['Audit Log Entries', $auditEntries],
            ['Click Statistics Entries', $clickEntries],
            ['Total Entries', $totalEntries],
            ['File Path', Storage::path($outputPath)],
            ['File Size', $this->formatBytes(Storage::size($outputPath))],
        ]);

        return self::SUCCESS;
    }

    /**
     * Handle data deletion (GDPR Article 17 - Right to Erasure)
     */
    private function handleDelete(): int
    {
        $user = $this->getTargetUser();
        if (! $user) {
            return self::FAILURE;
        }

        $this->warn("This will permanently delete ALL audit logs for user: {$user->name} (ID: {$user->id})");

        $logsCount = ChangeLog::byUser($user->id)->count();

        if ($logsCount === 0) {
            $this->info('No audit logs found for this user.');

            return self::SUCCESS;
        }

        $this->info("Found {$logsCount} audit log entries to delete.");

        if (! $this->option('force')) {
            if (! $this->confirm('Are you sure you want to permanently delete all audit logs for this user?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $deletedCount = ChangeLog::deleteUserData($user->id);

        $this->info("Successfully deleted {$deletedCount} audit log entries for user {$user->name}.");

        return self::SUCCESS;
    }

    /**
     * Handle data anonymization (Partial erasure while preserving audit integrity)
     */
    private function handleAnonymize(): int
    {
        $user = $this->getTargetUser();
        if (! $user) {
            return self::FAILURE;
        }

        $this->info("This will anonymize (remove personal identifiers from) audit logs and click statistics for user: {$user->name} (ID: {$user->id})");

        $logsCount = ChangeLog::byUser($user->id)->count();
        $clickStatsCount = ClickStat::whereNotNull('ip_address')
            ->orWhereNotNull('user_agent')
            ->orWhereNotNull('session_id')
            ->count();

        if ($logsCount === 0 && $clickStatsCount === 0) {
            $this->info('No audit logs or click statistics found to anonymize.');

            return self::SUCCESS;
        }

        $this->info("Found {$logsCount} audit log entries and {$clickStatsCount} click statistics entries to anonymize.");
        $this->warn('This will remove user_id, IP addresses, user agents, and session IDs while preserving audit trail integrity and statistical data.');

        if (! $this->option('force')) {
            if (! $this->confirm('Continue with anonymization?')) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $anonymizedAuditCount = ChangeLog::anonymizeUserData($user->id);
        $anonymizedClickCount = ClickStat::anonymizePersonalData();

        $this->info("Successfully anonymized {$anonymizedAuditCount} audit log entries and {$anonymizedClickCount} click statistics entries for user {$user->name}.");

        return self::SUCCESS;
    }

    /**
     * Handle privacy compliance report
     */
    private function handleReport(): int
    {
        $this->info('Generating privacy compliance report for audit logs and click statistics...');

        // Count personal data entries in audit logs
        $personalDataCount = ChangeLog::getPersonalDataLogs()->count();
        $totalLogsCount = ChangeLog::count();
        $personalDataPercentage = $totalLogsCount > 0 ? round(($personalDataCount / $totalLogsCount) * 100, 2) : 0;

        // Count sensitive model logs
        $sensitiveModelCount = ChangeLog::getSensitiveModelLogs()->count();
        $sensitiveModelPercentage = $totalLogsCount > 0 ? round(($sensitiveModelCount / $totalLogsCount) * 100, 2) : 0;

        // Count logs with IP addresses
        $ipAddressCount = ChangeLog::whereRaw("context->'ip_address' IS NOT NULL")->count();
        $ipAddressPercentage = $totalLogsCount > 0 ? round(($ipAddressCount / $totalLogsCount) * 100, 2) : 0;

        // Count anonymized logs
        $anonymizedCount = ChangeLog::whereRaw("context->'anonymized' = 'true'")->count();
        $anonymizedPercentage = $totalLogsCount > 0 ? round(($anonymizedCount / $totalLogsCount) * 100, 2) : 0;

        // Click statistics privacy metrics
        $totalClickStats = ClickStat::count();
        $clickStatsWithIP = ClickStat::whereNotNull('ip_address')->count();
        $clickStatsWithUserAgent = ClickStat::whereNotNull('user_agent')->count();
        $clickStatsWithSession = ClickStat::whereNotNull('session_id')->count();
        $anonymizedClickStats = ClickStat::where('session_id', 'like', 'anonymized_%')->count();

        $clickStatsIPPercentage = $totalClickStats > 0 ? round(($clickStatsWithIP / $totalClickStats) * 100, 2) : 0;
        $clickStatsAnonymizedPercentage = $totalClickStats > 0 ? round(($anonymizedClickStats / $totalClickStats) * 100, 2) : 0;

        // Oldest and newest entries
        $oldestEntry = ChangeLog::orderBy('timestamp')->first();
        $newestEntry = ChangeLog::orderByDesc('timestamp')->first();

        // Calculate retention periods
        $retentionConfig = config('audit.retention', []);

        $this->info('Audit Logs Privacy Metrics:');
        $this->table(['Metric', 'Value', 'Percentage'], [
            ['Total Audit Logs', number_format($totalLogsCount), '100%'],
            ['Logs with Personal Data', number_format($personalDataCount), "{$personalDataPercentage}%"],
            ['Sensitive Model Logs', number_format($sensitiveModelCount), "{$sensitiveModelPercentage}%"],
            ['Logs with IP Addresses', number_format($ipAddressCount), "{$ipAddressPercentage}%"],
            ['Anonymized Logs', number_format($anonymizedCount), "{$anonymizedPercentage}%"],
        ]);

        $this->info('');
        $this->info('Click Statistics Privacy Metrics:');
        $this->table(['Metric', 'Value', 'Percentage'], [
            ['Total Click Statistics', number_format($totalClickStats), '100%'],
            ['Stats with IP Addresses', number_format($clickStatsWithIP), "{$clickStatsIPPercentage}%"],
            ['Stats with User Agents', number_format($clickStatsWithUserAgent), $totalClickStats > 0 ? round(($clickStatsWithUserAgent / $totalClickStats) * 100, 2) . '%' : '0%'],
            ['Stats with Session IDs', number_format($clickStatsWithSession), $totalClickStats > 0 ? round(($clickStatsWithSession / $totalClickStats) * 100, 2) . '%' : '0%'],
            ['Anonymized Click Stats', number_format($anonymizedClickStats), "{$clickStatsAnonymizedPercentage}%"],
        ]);

        // Click type breakdown
        $pageViews = ClickStat::where('type', ClickStat::TYPE_PAGE_VIEW)->count();
        $externalClicks = ClickStat::where('type', ClickStat::TYPE_EXTERNAL_PROJECT)->count();
        $customLinkClicks = ClickStat::where('type', ClickStat::TYPE_CUSTOM_LINK)->count();

        $this->info('');
        $this->info('Click Types Breakdown:');
        $this->table(['Click Type', 'Count', 'Percentage'], [
            ['Page Views', number_format($pageViews), $totalClickStats > 0 ? round(($pageViews / $totalClickStats) * 100, 2) . '%' : '0%'],
            ['External Project Visits', number_format($externalClicks), $totalClickStats > 0 ? round(($externalClicks / $totalClickStats) * 100, 2) . '%' : '0%'],
            ['Custom Link Downloads', number_format($customLinkClicks), $totalClickStats > 0 ? round(($customLinkClicks / $totalClickStats) * 100, 2) . '%' : '0%'],
        ]);

        $this->info('');
        $this->info('Retention Configuration:');
        $this->table(['Setting', 'Value'], [
            ['General Retention', ($retentionConfig['days'] ?? 'Not set') . ' days'],
            ['Sensitive Data Retention', ($retentionConfig['sensitive_data_retention_days'] ?? 'Not set') . ' days'],
            ['IP Address Retention', ($retentionConfig['ip_address_retention_days'] ?? 'Not set') . ' days'],
            ['Cleanup Command Enabled', ($retentionConfig['cleanup_command'] ?? false) ? 'Yes' : 'No'],
        ]);

        if ($oldestEntry && $newestEntry) {
            $this->info('');
            $this->info('Data Range:');
            $this->table(['Period', 'Date'], [
                ['Oldest Entry', $oldestEntry->timestamp->format('Y-m-d H:i:s')],
                ['Newest Entry', $newestEntry->timestamp->format('Y-m-d H:i:s')],
                ['Total Span', $oldestEntry->timestamp->diffForHumans($newestEntry->timestamp, true)],
            ]);
        }

        // Privacy configuration
        $privacyConfig = config('audit.privacy', []);
        $this->info('');
        $this->info('Privacy Configuration:');
        $this->table(['Setting', 'Value'], [
            ['IP Anonymization', ($privacyConfig['anonymize_ip_addresses'] ?? false) ? 'Enabled' : 'Disabled'],
            ['Anonymization Method', $privacyConfig['ip_anonymization_method'] ?? 'Not set'],
            ['Data Export Enabled', ($privacyConfig['enable_data_export'] ?? false) ? 'Yes' : 'No'],
            ['Data Deletion Enabled', ($privacyConfig['enable_data_deletion'] ?? false) ? 'Yes' : 'No'],
        ]);

        return self::SUCCESS;
    }

    /**
     * Handle invalid action
     */
    private function handleInvalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->info('Available actions: export, delete, anonymize, report');

        return self::FAILURE;
    }

    /**
     * Get the target user for operations
     */
    private function getTargetUser(): ?User
    {
        $userId = $this->option('user-id');
        $email = $this->option('email');

        if (! $userId && ! $email) {
            $this->error('Either --user-id or --email must be provided.');

            return null;
        }

        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User with ID {$userId} not found.");

                return null;
            }
        } else {
            $user = User::where('email', $email)->first();
            if (! $user) {
                $this->error("User with email {$email} not found.");

                return null;
            }
        }

        return $user;
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return round($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
}
