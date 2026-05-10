<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ClickStat;
use App\Services\IpAnonymizationService;
use Exception;
use Illuminate\Console\Command;

class AnonymizeClickStatIPs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'click-stats:anonymize-ips
                           {--batch-size=1000 : Number of records to process in each batch}
                           {--force : Skip confirmation prompts}
                           {--dry-run : Show what would be anonymized without making changes}';

    /**
     * The console command description.
     */
    protected $description = 'Anonymize existing IP addresses in click statistics for GDPR compliance';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isDryRun = $this->option('dry-run');
        $batchSize = (int) $this->option('batch-size');

        if ($batchSize < 1 || $batchSize > 10000) {
            $this->error('Batch size must be between 1 and 10000');

            return self::FAILURE;
        }

        $this->info('Analyzing click statistics with IP addresses...');

        // Count records with non-anonymized IP addresses
        $totalRecords = ClickStat::whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->whereNotIn('ip_address', ['127.0.0.1', '::1']) // Skip localhost
            ->get()
            ->filter(function ($record) {
                return ! IpAnonymizationService::isAnonymized($record->ip_address);
            })
            ->count();

        if ($totalRecords === 0) {
            $this->info('No IP addresses found that need anonymization.');

            return self::SUCCESS;
        }

        $this->info("Found {$totalRecords} click statistics with IP addresses that need anonymization.");

        if ($isDryRun) {
            $this->info('DRY RUN MODE - Showing what would be anonymized:');

            // Show some examples
            $sampleRecords = ClickStat::whereNotNull('ip_address')
                ->where('ip_address', '!=', '')
                ->whereNotIn('ip_address', ['127.0.0.1', '::1'])
                ->limit(50)
                ->get(['id', 'ip_address', 'clicked_at'])
                ->filter(function ($record) {
                    return ! IpAnonymizationService::isAnonymized($record->ip_address);
                })
                ->take(10);

            $examples = [];
            foreach ($sampleRecords as $record) {
                $anonymized = IpAnonymizationService::anonymize($record->ip_address, 'subnet');
                $examples[] = [
                    $record->id,
                    $record->ip_address,
                    $anonymized,
                    $record->clicked_at->format('Y-m-d H:i:s'),
                ];
            }

            $this->table(['ID', 'Original IP', 'Anonymized IP', 'Clicked At'], $examples);

            $this->info("Total records that would be updated: {$totalRecords}");

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('This will permanently anonymize IP addresses in your click statistics.');
            $this->warn('Anonymized IPs cannot be restored to their original form.');

            if (! $this->confirm("Do you want to anonymize {$totalRecords} IP addresses?")) {
                $this->info('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $this->info('Starting IP address anonymization...');

        $processed = 0;
        $errors = 0;
        $progressBar = $this->output->createProgressBar($totalRecords);
        $progressBar->start();

        // Process in batches to avoid memory issues
        ClickStat::whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->whereNotIn('ip_address', ['127.0.0.1', '::1'])
            ->orderBy('id')
            ->chunk($batchSize, function ($records) use (&$processed, &$errors, $progressBar) {
                foreach ($records as $record) {
                    try {
                        $originalIp = $record->ip_address;

                        // Skip if already anonymized
                        if (IpAnonymizationService::isAnonymized($originalIp)) {
                            continue;
                        }

                        $anonymizedIp = IpAnonymizationService::anonymize($originalIp, 'subnet');

                        // Update the record
                        $record->update(['ip_address' => $anonymizedIp]);

                        $processed++;
                        $progressBar->advance();

                    } catch (Exception $e) {
                        $errors++;
                        $this->line(''); // New line to not interfere with progress bar
                        $this->error("Error processing record {$record->id}: ".$e->getMessage());
                    }
                }
            });

        $progressBar->finish();
        $this->line(''); // New line after progress bar

        $this->info('IP address anonymization completed!');
        $this->info("Successfully processed: {$processed} records");

        if ($errors > 0) {
            $this->warn("Errors encountered: {$errors} records");
        }

        // Show some statistics
        $remainingRecords = ClickStat::whereNotNull('ip_address')
            ->where('ip_address', '!=', '')
            ->whereNotIn('ip_address', ['127.0.0.1', '::1'])
            ->get()
            ->filter(function ($record) {
                return ! IpAnonymizationService::isAnonymized($record->ip_address);
            })
            ->count();

        if ($remainingRecords > 0) {
            $this->warn("Warning: {$remainingRecords} records still have non-anonymized IP addresses.");
            $this->warn('You may need to run this command again or investigate manually.');
        } else {
            $this->info('All IP addresses have been successfully anonymized!');
        }

        return self::SUCCESS;
    }
}
