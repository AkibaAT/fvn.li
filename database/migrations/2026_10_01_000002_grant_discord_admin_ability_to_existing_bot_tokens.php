<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('personal_access_tokens')
            ->join('users', function ($join) {
                $join->on('users.id', '=', 'personal_access_tokens.tokenable_id')
                    ->where('personal_access_tokens.tokenable_type', '=', User::class);
            })
            ->where('users.is_admin', true)
            ->select('personal_access_tokens.id', 'personal_access_tokens.abilities')
            ->orderBy('personal_access_tokens.id')
            ->chunk(100, function ($tokens) {
                foreach ($tokens as $token) {
                    $abilities = json_decode($token->abilities, true);
                    if (! in_array('discord-bot', $abilities, true) || in_array('discord-admin', $abilities, true)) {
                        continue;
                    }
                    DB::table('personal_access_tokens')->where('id', $token->id)
                        ->update(['abilities' => json_encode([...$abilities, 'discord-admin'])]);
                }
            });
    }

    public function down(): void {}
};
