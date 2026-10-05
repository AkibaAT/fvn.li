<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class IssueDiscordBotApiToken extends Command
{
    protected $signature = 'discord:issue-api-token
        {email : Email of the local user that owns the service token}
        {--name=fvn-discord-bot : Token name}
        {--replace : Revoke existing tokens with the same name first}';

    protected $description = 'Issue a Sanctum bearer token for the Discord bot APIs';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $name = (string) $this->option('name');
        $user = User::where('email', $email)->first();

        if (! $user) {
            $this->error("User with email {$email} not found.");

            return self::FAILURE;
        }

        if ($this->option('replace')) {
            $user->tokens()->where('name', $name)->delete();
        }

        $abilities = [
            'discord-bot',
            'discord-notifications',
            ...($user->is_admin ? ['discord-admin'] : []),
        ];
        $token = $user->createToken($name, $abilities);

        $this->info('Discord bot API token created.');
        $this->line('Abilities: ' . implode(', ', $abilities));
        $this->warn('Copy this token now; it will not be shown again.');
        $this->line($token->plainTextToken);

        return self::SUCCESS;
    }
}
