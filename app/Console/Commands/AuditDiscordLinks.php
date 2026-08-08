<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\SocialAccount;
use App\Models\UserNotificationPreferences;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AuditDiscordLinks extends Command
{
    protected $signature = 'notifications:audit-discord-links {--apply : Unlink invalid Discord accounts and mark DM delivery unavailable}';

    protected $description = 'Audit Discord social-account IDs that cannot be valid snowflakes';

    public function handle(): int
    {
        $invalid = SocialAccount::query()
            ->where('provider_name', 'discord')
            ->orderBy('id')
            ->get()
            ->filter(fn (SocialAccount $account): bool => ! $this->isValidSnowflake((string) $account->provider_id));

        if ($invalid->isEmpty()) {
            $this->info('No invalid Discord links found.');

            return self::SUCCESS;
        }

        $this->table(['account_id', 'user_id', 'provider_id'], $invalid->map(fn (SocialAccount $account): array => [
            $account->id,
            $account->user_id,
            $account->provider_id,
        ])->all());

        if (! $this->option('apply')) {
            $this->warn("Found {$invalid->count()} invalid Discord link(s). Re-run with --apply to unlink them.");

            return self::FAILURE;
        }

        foreach ($invalid as $account) {
            DB::transaction(function () use ($account): void {
                UserNotificationPreferences::firstOrCreate(
                    ['user_id' => $account->user_id],
                    ['notification_digest' => 'asap'],
                )->markDiscordUndeliverable('not_linked');

                $account->delete();
            });
        }

        $this->info("Unlinked {$invalid->count()} invalid Discord account(s).");

        return self::SUCCESS;
    }

    private function isValidSnowflake(string $value): bool
    {
        if (! preg_match('/^[0-9]{1,20}$/', $value)) {
            return false;
        }

        $normalized = ltrim($value, '0') ?: '0';
        $maxSignedBigint = '9223372036854775807';

        return strlen($normalized) < strlen($maxSignedBigint)
            || (strlen($normalized) === strlen($maxSignedBigint) && strcmp($normalized, $maxSignedBigint) <= 0);
    }
}
