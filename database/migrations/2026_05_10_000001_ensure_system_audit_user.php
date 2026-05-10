<?php

declare(strict_types=1);

use App\Support\SystemAuditUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $configuredSystemUserId = config('audit.system_user_id');

        if (
            is_numeric($configuredSystemUserId)
            && ! DB::table('users')->where('id', (int) $configuredSystemUserId)->exists()
        ) {
            DB::table('users')->insert([
                'id' => (int) $configuredSystemUserId,
                'name' => SystemAuditUser::NAME,
                'email' => SystemAuditUser::EMAIL,
                'password' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->advanceUsersSequence();

            return;
        }

        if (DB::table('users')->whereIn('email', [SystemAuditUser::EMAIL, SystemAuditUser::LEGACY_EMAIL])->exists()) {
            return;
        }

        DB::table('users')->insert([
            'name' => SystemAuditUser::NAME,
            'email' => SystemAuditUser::EMAIL,
            'password' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->advanceUsersSequence();
    }

    public function down(): void
    {
        // Keep the system audit user to preserve foreign-key integrity.
    }

    private function advanceUsersSequence(): void
    {
        DB::statement(
            "SELECT setval(
                'users_id_seq',
                GREATEST((SELECT COALESCE(MAX(id), 1) FROM users), (SELECT last_value FROM users_id_seq)),
                true
            )"
        );
    }
};
