<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SocialImageService;
use Illuminate\Console\Command;

class CleanupSocialImages extends Command
{
    protected $signature = 'app:cleanup-social-images';

    protected $description = 'Clean up old cached social media images';

    public function handle(SocialImageService $socialImageService): int
    {
        $this->info('Cleaning up old social media images...');

        $socialImageService->cleanupOldImages();

        $this->info('Social media image cleanup completed.');

        return self::SUCCESS;
    }
}
