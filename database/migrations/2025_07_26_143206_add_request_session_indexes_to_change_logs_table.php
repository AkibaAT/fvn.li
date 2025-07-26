<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add indexes for request_id and session_id to optimize audit log queries
        // These allow grouping and filtering audit logs by request or session

        // Index for request ID (for tracing all changes within a single request)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_change_logs_request_id ON change_logs USING GIN ((context->\'request_id\'))');

        // Index for session ID (for tracing all changes within a user session)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_change_logs_session_id ON change_logs USING GIN ((context->\'session_id\'))');

        // Composite index for request ID + timestamp (for chronological request tracing)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_change_logs_request_time ON change_logs ((context->\'request_id\'), timestamp)');

        // Composite index for session ID + timestamp (for chronological session tracing)
        DB::statement('CREATE INDEX IF NOT EXISTS idx_change_logs_session_time ON change_logs ((context->\'session_id\'), timestamp)');
    }

    public function down(): void
    {
        // Drop the indexes
        DB::statement('DROP INDEX IF EXISTS idx_change_logs_request_id');
        DB::statement('DROP INDEX IF EXISTS idx_change_logs_session_id');
        DB::statement('DROP INDEX IF EXISTS idx_change_logs_request_time');
        DB::statement('DROP INDEX IF EXISTS idx_change_logs_session_time');
    }
};
