<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateChangeLogPartitions extends Command
{
    protected $signature = 'audit:create-partitions
        {--months=1 : Number of months ahead to create partitions for}
        {--year= : Create all partitions for a specific year (overrides --months)}';

    protected $description = 'Create monthly partitions for the change_logs table';

    public function handle(): int
    {
        // If --year is specified, create all 12 months for that year
        if ($this->option('year')) {
            return $this->createYearPartitions((int) $this->option('year'));
        }

        // Otherwise, create partitions for the next N months
        return $this->createUpcomingPartitions((int) $this->option('months'));
    }

    private function createUpcomingPartitions(int $monthsAhead): int
    {
        $this->info("Creating change_logs partitions for current month + next {$monthsAhead} month(s)...");

        $created = 0;
        $skipped = 0;
        $errors = 0;

        // Start from current month (i=0) to ensure it exists, then create N months ahead
        for ($i = 0; $i <= $monthsAhead; $i++) {
            $date = Carbon::now()->addMonths($i)->startOfMonth();
            $result = $this->createPartition($date->year, $date->month);

            match ($result) {
                'created' => $created++,
                'skipped' => $skipped++,
                'error' => $errors++,
            };
        }

        $this->newLine();
        $this->info("Summary: {$created} created, {$skipped} skipped, {$errors} errors");

        return $errors > 0 ? 1 : 0;
    }

    private function createYearPartitions(int $year): int
    {
        $this->info("Creating change_logs partitions for year {$year}...");

        $created = 0;
        $skipped = 0;
        $errors = 0;

        for ($month = 1; $month <= 12; $month++) {
            $result = $this->createPartition($year, $month);

            match ($result) {
                'created' => $created++,
                'skipped' => $skipped++,
                'error' => $errors++,
            };
        }

        $this->newLine();
        $this->info("Summary: {$created} created, {$skipped} skipped, {$errors} errors");

        if ($errors === 0) {
            Log::info("Created change_logs partitions for year {$year}", [
                'created' => $created,
                'skipped' => $skipped,
            ]);
        }

        return $errors > 0 ? 1 : 0;
    }

    private function createPartition(int $year, int $month): string
    {
        $partitionName = sprintf('change_logs_y%dm%02d', $year, $month);
        $startDate = sprintf('%d-%02d-01', $year, $month);

        // Calculate end date (first day of next month)
        if ($month == 12) {
            $endDate = sprintf('%d-01-01', $year + 1);
        } else {
            $endDate = sprintf('%d-%02d-01', $year, $month + 1);
        }

        // Check if partition already exists
        $exists = DB::selectOne(
            'SELECT 1 FROM pg_tables WHERE tablename = ?',
            [$partitionName]
        );

        if ($exists) {
            $this->line("  Skipped {$partitionName} (already exists)");

            return 'skipped';
        }

        $sql = "CREATE TABLE IF NOT EXISTS {$partitionName} PARTITION OF change_logs
                FOR VALUES FROM ('{$startDate}') TO ('{$endDate}')";

        try {
            DB::statement($sql);
            $this->info("  Created {$partitionName}");

            Log::info('Created change_logs partition', [
                'partition' => $partitionName,
                'range' => "{$startDate} to {$endDate}",
            ]);

            return 'created';
        } catch (Exception $e) {
            $this->error("  Failed to create {$partitionName}: " . $e->getMessage());

            Log::error('Failed to create change_logs partition', [
                'partition' => $partitionName,
                'error' => $e->getMessage(),
            ]);

            return 'error';
        }
    }
}
