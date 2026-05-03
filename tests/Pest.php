<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class);

function useExternalPestBrowserServers(): void
{
    if (! class_exists(Pest\Browser\ServerManager::class)) {
        return;
    }

    $baseUrl = getenv('PEST_BROWSER_BASE_URL') ?: null;
    $playwrightHost = getenv('PEST_BROWSER_PLAYWRIGHT_HOST') ?: null;
    $playwrightPort = (int) (getenv('PEST_BROWSER_PLAYWRIGHT_PORT') ?: 3000);

    if (! $baseUrl && ! $playwrightHost) {
        return;
    }

    $serverManager = Pest\Browser\ServerManager::instance();
    $reflection = new ReflectionClass($serverManager);

    if ($playwrightHost) {
        $playwrightProperty = $reflection->getProperty('playwright');
        $playwrightProperty->setValue(
            $serverManager,
            new Pest\Browser\Playwright\Servers\AlreadyStartedPlaywrightServer($playwrightHost, $playwrightPort),
        );
    }

    if ($baseUrl) {
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setValue($serverManager, new Tests\Support\ExternalBrowserHttpServer($baseUrl));
    }
}

useExternalPestBrowserServers();

// Use RefreshDatabase for Feature tests
pest()->group('Feature')
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class);

// Browser tests also run against isolated test data.
pest()->group('Browser')
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}
