<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Change the column type from CHAR(255) to VARCHAR(255)
        DB::statement('ALTER TABLE users ALTER COLUMN avatar TYPE VARCHAR(255)');

        // Trim the values to remove padding spaces
        DB::statement('UPDATE users SET avatar = TRIM(avatar) WHERE avatar IS NOT NULL');
    }

    public function down(): void
    {
        // Change the column type back to CHAR(255)
        DB::statement('ALTER TABLE users ALTER COLUMN avatar TYPE CHAR(255)');
    }
};
