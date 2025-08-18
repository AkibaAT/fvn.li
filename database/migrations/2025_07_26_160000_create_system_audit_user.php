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
        $now = now();
        DB::table('users')->updateOrInsert(
            ['email' => 'system+anonymized@fvn.li'],
            [
                'name' => 'System (Anonymized)',
                'email_verified_at' => $now,
                'password' => '', // Empty password - this user cannot log in
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );
    }

    public function down(): void
    {
        // Remove the system user (located by email) if it has no non-anonymized audit logs
        $system = DB::table('users')->where('email', 'system+anonymized@fvn.li')->first();
        if ($system) {
            $nonAnonymizedLogs = DB::table('change_logs')
                ->where('user_id', $system->id)
                ->whereRaw("context->'anonymized' IS NULL")
                ->count();

            if ($nonAnonymizedLogs === 0) {
                DB::table('users')->where('id', $system->id)->delete();
            }
        }
    }
};
