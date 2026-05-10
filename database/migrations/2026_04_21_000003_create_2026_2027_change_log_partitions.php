<?php

declare(strict_types=1);

use App\Support\SystemAuditUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $this->ensureSystemAuditUser();

        if (! $this->changeLogsTableExists()) {
            return;
        }

        foreach ([2026, 2027] as $year) {
            for ($month = 1; $month <= 12; $month++) {
                $this->createPartition($year, $month);
            }
        }
    }

    public function down(): void
    {
        // Intentionally keep audit log partitions and their data.
    }

    private function changeLogsTableExists(): bool
    {
        return DB::selectOne("SELECT to_regclass('change_logs') AS table_name")->table_name !== null;
    }

    private function ensureSystemAuditUser(): void
    {
        $systemUserId = config('audit.system_user_id');

        if (
            is_numeric($systemUserId)
            && DB::table('users')->where('id', (int) $systemUserId)->exists()
        ) {
            return;
        }

        if (DB::table('users')->whereIn('email', [SystemAuditUser::EMAIL, SystemAuditUser::LEGACY_EMAIL])->exists()) {
            return;
        }

        $user = [
            'name' => SystemAuditUser::NAME,
            'email' => SystemAuditUser::EMAIL,
            'password' => '',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        if (is_numeric($systemUserId)) {
            $user['id'] = (int) $systemUserId;
        }

        DB::table('users')->insert($user);

        DB::statement(
            "SELECT setval(
                'users_id_seq',
                GREATEST((SELECT COALESCE(MAX(id), 1) FROM users), (SELECT last_value FROM users_id_seq)),
                true
            )"
        );
    }

    private function createPartition(int $year, int $month): void
    {
        $partitionName = sprintf('change_logs_y%dm%02d', $year, $month);
        $startDate = sprintf('%d-%02d-01', $year, $month);
        $endDate = $month === 12
            ? sprintf('%d-01-01', $year + 1)
            : sprintf('%d-%02d-01', $year, $month + 1);

        DB::statement(
            "CREATE TABLE IF NOT EXISTS {$partitionName} PARTITION OF change_logs
                FOR VALUES FROM ('{$startDate}') TO ('{$endDate}')"
        );
    }
};
