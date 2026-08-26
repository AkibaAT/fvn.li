<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->index()->constrained()->nullOnDelete();
        });

        DB::statement("
            UPDATE raters
            SET user_id = social_accounts.user_id
            FROM social_accounts
            WHERE social_accounts.provider_name = 'itchio'
              AND raters.itch_id IS NOT NULL
              AND social_accounts.provider_id = raters.itch_id::text
        ");

        DB::statement("
            UPDATE raters
            SET user_id = social_accounts.user_id
            FROM social_accounts
            WHERE social_accounts.provider_name = 'steam'
              AND raters.steam_id IS NOT NULL
              AND social_accounts.provider_id = raters.steam_id
              AND raters.user_id IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('raters', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
