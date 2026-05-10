<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;

class GenerateWebPushKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webpush:generate-keys';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate VAPID keys for Web Push Notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Generating VAPID keys...');

        try {
            $keysArray = VAPID::createVapidKeys();

            $this->info('VAPID keys generated successfully!');
            $this->newLine();

            $this->info('Public Key:');
            $this->line($keysArray['publicKey']);
            $this->newLine();

            $this->info('Private Key:');
            $this->line($keysArray['privateKey']);
            $this->newLine();

            $this->info('Add these keys to your .env file:');
            $this->line('VAPID_PUBLIC_KEY='.$keysArray['publicKey']);
            $this->line('VAPID_PRIVATE_KEY='.$keysArray['privateKey']);
            $this->line('VAPID_SUBJECT=mailto:your-email@example.com');

            return 0;
        } catch (Exception $e) {
            $this->error('Failed to generate VAPID keys: '.$e->getMessage());

            return 1;
        }
    }
}
