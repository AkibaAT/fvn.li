<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add created_at and updated_at columns to change_logs table
        DB::statement('ALTER TABLE change_logs ADD COLUMN created_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP');
        DB::statement('ALTER TABLE change_logs ADD COLUMN updated_at TIMESTAMPTZ DEFAULT CURRENT_TIMESTAMP');

        // Set created_at to the timestamp value for existing records
        DB::statement('UPDATE change_logs SET created_at = timestamp WHERE created_at IS NULL');

        // Set updated_at to the timestamp value for existing records (no modifications yet)
        DB::statement('UPDATE change_logs SET updated_at = timestamp WHERE updated_at IS NULL');

        // Make the columns NOT NULL after setting default values
        DB::statement('ALTER TABLE change_logs ALTER COLUMN created_at SET NOT NULL');
        DB::statement('ALTER TABLE change_logs ALTER COLUMN updated_at SET NOT NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE change_logs DROP COLUMN IF EXISTS created_at');
        DB::statement('ALTER TABLE change_logs DROP COLUMN IF EXISTS updated_at');
    }
};
