<?php

declare(strict_types=1);

use App\Traits\HasSocialMetaTags;
use Illuminate\Http\Request;

function createTestClass()
{
    return new class
    {
        use HasSocialMetaTags;

        public function testIsSocialMediaCrawler()
        {
            return $this->isSocialMediaCrawler();
        }

        public function testShouldGenerateSocialPreview()
        {
            return $this->shouldGenerateSocialPreview();
        }
    };
}

dataset('social_crawler_user_agents', [
    // Facebook crawlers
    ['facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)', true],
    ['Facebot Tester', true],

    // Twitter/X crawlers
    ['Twitterbot/1.0', true],

    // LinkedIn
    ['LinkedInBot/1.0 (compatible; Mozilla/5.0; Apache-HttpClient +http://www.linkedin.com)', true],

    // WhatsApp
    ['WhatsApp/2.23.2.72', true],

    // Slack
    ['Slackbot-LinkExpanding 1.0 (+https://api.slack.com/robots)', true],

    // Discord
    ['Mozilla/5.0 (compatible; Discordbot/2.0; +https://discordapp.com)', true],

    // BlueSky
    ['Mozilla/5.0 (compatible; Bluesky Cardyb/1.1; +mailto:support@bsky.app)', true],

    // Pinterest
    ['Mozilla/5.0 (compatible; Pinterestbot/1.0; +http://www.pinterest.com/bot.html)', true],

    // Snapchat
    ['Snap URL Preview Service; bot; snapchat; https://developers.snap.com/robots', true],

    // Viber
    ['Viber', true],

    // Odnoklassniki
    ['OdklBot/1.0 (share@odnoklassniki.ru)', true],

    // Internet Archive
    ['ia_archiver (+http://www.alexa.com/site/help/webmasters; crawler@alexa.com)', true],

    // Regular browsers (should not be detected)
    ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36', false],
    ['Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36', false],
    ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36', false],

    // Empty user agent
    ['', false],
]);

it('detects social media crawlers', function ($userAgent, $expected) {
    // Create a new request with the user agent
    $request = Request::create('/', 'GET');
    $request->headers->set('User-Agent', $userAgent);

    // Replace the app's request instance
    $this->app->instance('request', $request);

    $testClass = createTestClass();

    expect($testClass->testIsSocialMediaCrawler())->toBe($expected);
})->with('social_crawler_user_agents');

it('detects social preview parameter', function () {
    // Test without parameter
    $request = Request::create('/', 'GET');
    $this->app->instance('request', $request);

    $testClass = createTestClass();
    expect($testClass->testShouldGenerateSocialPreview())->toBeFalse();

    // Test with parameter set to 1
    $request = Request::create('/', 'GET', ['social_preview' => '1']);
    $this->app->instance('request', $request);

    $testClass = createTestClass();
    expect($testClass->testShouldGenerateSocialPreview())->toBeTrue();

    // Test with parameter set to 0
    $request = Request::create('/', 'GET', ['social_preview' => '0']);
    $this->app->instance('request', $request);

    $testClass = createTestClass();
    expect($testClass->testShouldGenerateSocialPreview())->toBeFalse();
});
