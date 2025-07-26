<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Create a system user for anonymized audit logs
        // This preserves audit trail integrity while removing personal identifiers
        DB::table('users')->insertOrIgnore([
            'id' => 1,
            'name' => 'System (Anonymized)',
            'email' => 'system+anonymized@fvn.li',
            'email_verified_at' => now(),
            'password' => '', // Empty password - this user cannot log in
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Remove the system user if it exists and has no non-anonymized audit logs
        $systemUserId = 1;

        // Check if there are any audit logs that aren't anonymized for this user
        $nonAnonymizedLogs = DB::table('change_logs')
            ->where('user_id', $systemUserId)
            ->whereRaw("context->'anonymized' IS NULL")
            ->count();

        // Only delete if no non-anonymized logs exist (all logs are anonymized)
        if ($nonAnonymizedLogs === 0) {
            DB::table('users')->where('id', $systemUserId)->delete();
        }
    }
};
