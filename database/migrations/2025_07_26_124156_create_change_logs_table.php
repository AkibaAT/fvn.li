<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Create partitioned table using raw SQL since Laravel doesn't support partitioning directly
        DB::statement("
            CREATE TABLE change_logs (
                id BIGSERIAL,
                timestamp TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP,
                event_type VARCHAR(50) NOT NULL,
                entity_type VARCHAR(50) NOT NULL,
                entity_id BIGINT NOT NULL,
                user_id BIGINT REFERENCES users(id) ON DELETE SET NULL,
                changes JSONB,
                old_values JSONB,
                new_values JSONB,
                context JSONB,
                source VARCHAR(20) DEFAULT 'web',
                PRIMARY KEY (id, timestamp)
            ) PARTITION BY RANGE (timestamp)
        ");

        // Create indexes
        DB::statement('CREATE INDEX idx_change_logs_entity ON change_logs (entity_type, entity_id, timestamp)');
        DB::statement('CREATE INDEX idx_change_logs_user ON change_logs (user_id, timestamp)');
        DB::statement('CREATE INDEX idx_change_logs_event ON change_logs (event_type, timestamp)');
        DB::statement('CREATE INDEX idx_change_logs_timestamp ON change_logs (timestamp)');

        // Create initial monthly partitions for 2025
        $currentYear = date('Y');
        $currentMonth = date('n');

        for ($month = 1; $month <= 12; $month++) {
            $startDate = sprintf('%d-%02d-01', $currentYear, $month);
            $endDate = sprintf('%d-%02d-01', $month == 12 ? $currentYear + 1 : $currentYear,
                $month == 12 ? 1 : $month + 1);

            $monthPadded = sprintf('%02d', $month);
            DB::statement("
                CREATE TABLE change_logs_y{$currentYear}m{$monthPadded} PARTITION OF change_logs
                FOR VALUES FROM ('{$startDate}') TO ('{$endDate}')
            ");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('change_logs');
    }
};
