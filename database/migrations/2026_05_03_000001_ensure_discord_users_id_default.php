<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_class
        WHERE relkind = 'S'
            AND relname = 'discord_users_id_seq'
    ) THEN
        CREATE SEQUENCE discord_users_id_seq;
    END IF;
END
$$;
SQL);

        DB::statement("ALTER SEQUENCE discord_users_id_seq OWNED BY discord_users.id");
        DB::statement("ALTER TABLE discord_users ALTER COLUMN id SET DEFAULT nextval('discord_users_id_seq')");
        DB::statement("SELECT setval('discord_users_id_seq', COALESCE((SELECT MAX(id) FROM discord_users), 0) + 1, false)");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE discord_users ALTER COLUMN id DROP DEFAULT');
    }
};
