<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes for command tracking to optimize audit log queries

        // Index for command name (for filtering by specific commands)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_change_logs_command_name ON change_logs USING GIN ((context->\'command\'->\'name\'))');

        // Composite index for command name + timestamp (for chronological command tracing)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_change_logs_command_time ON change_logs ((context->\'command\'->\'name\'), timestamp)');
    }

    public function down(): void
    {
        // Drop the command indexes
        DB::statement('DROP INDEX IF EXISTS idx_change_logs_command_name');
        DB::statement('DROP INDEX IF EXISTS idx_change_logs_command_time');
    }
};
