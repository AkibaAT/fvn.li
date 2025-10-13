<?php

namespace Tests\Browser;

/**
 * Browser testing configuration for PEST 4
 */
class BrowserConfig
{
    /**
     * Configure browser for headed mode (visible browser)
     */
    public static function configureHeadedMode(): array
    {
        return [
            'headless' => false,
            'args' => [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-web-security',
                '--disable-features=VizDisplayCompositor',
                '--window-size=1920,1080',
                '--display=:99',
            ],
        ];
    }

    /**
     * Configure browser for headless mode (default)
     */
    public static function configureHeadlessMode(): array
    {
        return [
            'headless' => true,
            'args' => [
                '--no-sandbox',
                '--disable-dev-shm-usage',
                '--disable-gpu',
                '--disable-web-security',
                '--disable-features=VizDisplayCompositor',
                '--window-size=1920,1080',
            ],
        ];
    }

    /**
     * Get browser configuration based on environment
     */
    public static function getBrowserConfig(): array
    {
        // Check if headed mode is requested via environment variable
        $headed = env('BROWSER_HEADED', false);

        if ($headed) {
            return self::configureHeadedMode();
        }

        return self::configureHeadlessMode();
    }
}
