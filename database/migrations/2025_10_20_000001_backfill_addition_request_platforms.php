<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Backfill platform for existing addition requests based on their URLs
        DB::table('addition_requests')
            ->whereNull('platform')
            ->orWhere('platform', '')
            ->chunkById(100, function ($requests) {
                foreach ($requests as $request) {
                    $platform = $this->detectPlatform($request->game_url);
                    
                    DB::table('addition_requests')
                        ->where('id', $request->id)
                        ->update(['platform' => $platform]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No need to reverse - platform data is still valid
    }

    /**
     * Detect platform from URL.
     */
    private function detectPlatform(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);
        
        if (!$host) {
            return 'other';
        }

        // Remove www. prefix for matching
        $host = preg_replace('/^www\./', '', $host);

        // Check for itch.io
        if (str_ends_with($host, '.itch.io') || $host === 'itch.io') {
            return 'itch_io';
        }

        // Check for Steam
        if (str_contains($host, 'steampowered.com') || str_contains($host, 'store.steampowered.com')) {
            return 'steam';
        }

        return 'other';
    }
};

