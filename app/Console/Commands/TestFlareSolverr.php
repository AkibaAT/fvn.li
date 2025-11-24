<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\FlareSolverrClient;
use App\Services\ItchAuthService;
use Exception;
use Illuminate\Console\Command;

class TestFlareSolverr extends Command
{
    protected $signature = 'test:flaresolverr
        {--check-service : Only check if FlareSolverr service is available}
        {--test-auth : Test itch.io authentication}';

    protected $description = 'Test FlareSolverr integration';

    public function handle(FlareSolverrClient $flareSolverr, ItchAuthService $authService): int
    {
        $this->info('Testing FlareSolverr Integration');
        $this->newLine();

        // Check if FlareSolverr is enabled
        $enabled = config('services.flaresolverr.enabled', false);
        $this->info('FlareSolverr Enabled: ' . ($enabled ? 'Yes' : 'No'));

        if (! $enabled) {
            $this->warn('FlareSolverr is disabled in configuration');
            $this->info('Set FLARESOLVERR_ENABLED=true in .env to enable');

            return 1;
        }

        // Check service availability
        $this->info('Checking FlareSolverr service availability...');
        $available = $flareSolverr->isAvailable();

        if ($available) {
            $this->info('✓ FlareSolverr service is available');
        } else {
            $this->error('✗ FlareSolverr service is not available');
            $this->info('Make sure the FlareSolverr container is running');

            return 1;
        }

        if ($this->option('check-service')) {
            return 0;
        }

        // Test basic request
        $this->newLine();
        $this->info('Testing basic request to itch.io...');

        try {
            $result = $flareSolverr->request('https://itch.io/');

            if ($result['status'] === 200) {
                $this->info('✓ Successfully fetched itch.io homepage');
                $this->info('  Status: ' . $result['status']);
                $this->info('  User Agent: ' . $result['userAgent']);
                $this->info('  Cookies: ' . count($result['cookies']));
            } else {
                $this->error('✗ Unexpected status code: ' . $result['status']);

                return 1;
            }
        } catch (Exception $e) {
            $this->error('✗ Request failed: ' . $e->getMessage());

            return 1;
        }

        // Test authentication if requested
        if ($this->option('test-auth')) {
            $this->newLine();
            $this->info('Testing itch.io authentication...');

            try {
                $client = $authService->getClient();
                $this->info('✓ Successfully authenticated with itch.io');

                // Test accessing dashboard
                $response = $client->get('https://itch.io/dashboard');
                if ($response->getStatusCode() === 200) {
                    $this->info('✓ Successfully accessed dashboard');
                } else {
                    $this->error('✗ Failed to access dashboard: ' . $response->getStatusCode());

                    return 1;
                }
            } catch (Exception $e) {
                $this->error('✗ Authentication failed: ' . $e->getMessage());

                return 1;
            }
        }

        $this->newLine();
        $this->info('All tests passed!');

        return 0;
    }
}
