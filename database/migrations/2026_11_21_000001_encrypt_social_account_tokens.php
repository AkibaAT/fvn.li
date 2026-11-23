<?php

declare(strict_types=1);

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('social_accounts', function (Blueprint $table): void {
            $table->text('refresh_token')->nullable()->change();
        });

        DB::table('social_accounts')
            ->select(['id', 'token', 'refresh_token'])
            ->where(function ($query): void {
                $query->whereNotNull('token')
                    ->orWhereNotNull('refresh_token');
            })
            ->orderBy('id')
            ->chunkById(200, function ($accounts): void {
                foreach ($accounts as $account) {
                    $updates = [];

                    foreach (['token', 'refresh_token'] as $column) {
                        $value = $account->{$column};
                        if (! is_string($value) || $value === '') {
                            continue;
                        }

                        if (! $this->isEncrypted($value)) {
                            $updates[$column] = Crypt::encryptString($value);
                        }
                    }

                    if ($updates !== []) {
                        DB::table('social_accounts')
                            ->where('id', $account->id)
                            ->update($updates);
                    }
                }
            });
    }

    public function down(): void
    {
        DB::table('social_accounts')
            ->select(['id', 'token', 'refresh_token'])
            ->where(function ($query): void {
                $query->whereNotNull('token')
                    ->orWhereNotNull('refresh_token');
            })
            ->orderBy('id')
            ->chunkById(200, function ($accounts): void {
                foreach ($accounts as $account) {
                    $updates = [];

                    foreach (['token', 'refresh_token'] as $column) {
                        $value = $account->{$column};
                        if (! is_string($value) || $value === '') {
                            continue;
                        }

                        try {
                            $updates[$column] = Crypt::decryptString($value);
                        } catch (DecryptException) {
                            // Leave values that were already plaintext untouched.
                        }
                    }

                    if ($updates !== []) {
                        DB::table('social_accounts')
                            ->where('id', $account->id)
                            ->update($updates);
                    }
                }
            });
    }

    private function isEncrypted(string $value): bool
    {
        try {
            Crypt::decryptString($value);

            return true;
        } catch (DecryptException) {
            return false;
        }
    }
};
