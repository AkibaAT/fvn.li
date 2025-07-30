<?php

declare(strict_types=1);

use App\Livewire\GameList;
use App\Traits\HasSocialMetaTags;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

it('uses first thumbnail for non game list views', function () {
    // Create some test games with thumbnails
    $games = collect([
        (object) [
            'id' => 1,
            'name' => 'Test Game 1',
            'thumb_url' => 'https://example.com/thumb1.jpg',
            'updated_at' => now(),
        ],
        (object) [
            'id' => 2,
            'name' => 'Test Game 2',
            'thumb_url' => 'https://example.com/thumb2.jpg',
            'updated_at' => now(),
        ],
    ]);

    // Create a mock paginator
    $paginator = new LengthAwarePaginator(
        $games,
        2,
        9,
        1,
        ['path' => '/']
    );

    // Create a test class that uses the trait but is NOT GameList
    $testClass = new class
    {
        use HasSocialMetaTags;

        public $games;
        public $search = '';
        public $selectedStatuses = [];
        public $sortField = 'name';
        public $sortDirection = 'asc';

        public function testGetMetaImage()
        {
            return $this->getMetaImage();
        }

        public function testGetCurrentFilters()
        {
            return $this->getCurrentFilters();
        }
    };

    $testClass->games = $paginator;

    // Test that getCurrentFilters works
    $filters = $testClass->testGetCurrentFilters();
    expect($filters)->toBeArray();
    expect($filters['sort'])->toBe('name_asc');

    // Test with regular user agent - should use first thumbnail since this is NOT a GameList view
    $metaImage = $testClass->testGetMetaImage();
    expect($metaImage)->toBe('https://example.com/thumb1.jpg');
});

it('generates collage for facebook crawler on game list', function () {
    // Mock Facebook crawler user agent
    $this->app['request']->headers->set('User-Agent', 'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)');

    $games = collect([
        (object) [
            'id' => 1,
            'name' => 'Test Game 1',
            'thumb_url' => 'https://example.com/thumb1.jpg',
            'updated_at' => now(),
        ],
        (object) [
            'id' => 2,
            'name' => 'Test Game 2',
            'thumb_url' => 'https://example.com/thumb2.jpg',
            'updated_at' => now(),
        ],
    ]);

    $paginator = new LengthAwarePaginator($games, 2, 9, 1, ['path' => '/']);

    // Create a test class that extends GameList to simulate being on the list view
    $testClass = new class extends GameList
    {
        public function testGetMetaImage()
        {
            return $this->getMetaImage();
        }
    };

    $testClass->games = $paginator;

    // Should attempt to generate collage for GameList view with Facebook crawler
    $metaImage = $testClass->testGetMetaImage();
    expect($metaImage)->toBeString();
});

it('generates collage with preview parameter on game list', function () {
    // Mock request with social_preview=1 parameter
    $this->app['request']->merge(['social_preview' => '1']);

    $games = collect([
        (object) [
            'id' => 1,
            'name' => 'Test Game 1',
            'thumb_url' => 'https://example.com/thumb1.jpg',
            'updated_at' => now(),
        ],
        (object) [
            'id' => 2,
            'name' => 'Test Game 2',
            'thumb_url' => 'https://example.com/thumb2.jpg',
            'updated_at' => now(),
        ],
    ]);

    $paginator = new LengthAwarePaginator($games, 2, 9, 1, ['path' => '/']);

    // Create a test class that extends GameList to simulate being on the list view
    $testClass = new class extends GameList
    {
        public function testGetMetaImage()
        {
            return $this->getMetaImage();
        }
    };

    $testClass->games = $paginator;

    // Should attempt to generate collage because of preview parameter on GameList
    $metaImage = $testClass->testGetMetaImage();
    expect($metaImage)->toBeString();
});

it('uses first thumbnail for single game', function () {
    // Create a single game
    $games = collect([
        (object) [
            'id' => 1,
            'name' => 'Test Game 1',
            'thumb_url' => 'https://example.com/thumb1.jpg',
            'updated_at' => now(),
        ],
    ]);

    $paginator = new LengthAwarePaginator($games, 1, 9, 1, ['path' => '/']);

    $testClass = new class
    {
        use HasSocialMetaTags;

        public $games;

        public function testGetMetaImage()
        {
            return $this->getMetaImage();
        }
    };

    $testClass->games = $paginator;

    // For single game, should return the thumbnail URL directly
    $metaImage = $testClass->testGetMetaImage();
    expect($metaImage)->toBe('https://example.com/thumb1.jpg');
});

it('returns empty for no games', function () {
    $games = collect([]);
    $paginator = new LengthAwarePaginator($games, 0, 9, 1, ['path' => '/']);

    $testClass = new class
    {
        use HasSocialMetaTags;

        public $games;

        public function testGetMetaImage()
        {
            return $this->getMetaImage();
        }
    };

    $testClass->games = $paginator;

    $metaImage = $testClass->testGetMetaImage();
    expect($metaImage)->toBe('');
});

it('current filters includes all filter types', function () {
    $testClass = new class
    {
        use HasSocialMetaTags;

        public $search = 'test search';
        public $selectedStatuses = ['completed'];
        public $selectedEngines = ['Ren\'Py'];
        public $selectedPlatforms = ['windows', 'linux'];
        public $selectedLanguages = ['eng'];
        public $selectedGameJams = ['1'];
        public $selectedTags = ['2'];
        public $nsfw = true;
        public $sfw = false;
        public $sortField = 'rating';
        public $sortDirection = 'desc';

        public function testGetCurrentFilters()
        {
            return $this->getCurrentFilters();
        }
    };

    $filters = $testClass->testGetCurrentFilters();

    expect($filters['search'])->toBe('test search');
    expect($filters['statuses'])->toBe(['completed']);
    expect($filters['engines'])->toBe(['Ren\'Py']);
    expect($filters['platforms'])->toBe(['windows', 'linux']);
    expect($filters['languages'])->toBe(['eng']);
    expect($filters['gamejams'])->toBe(['1']);
    expect($filters['tags'])->toBe(['2']);
    expect($filters['nsfw'])->toBeTrue();
    expect($filters['sort'])->toBe('rating_desc');
    expect($filters)->not->toHaveKey('sfw'); // sfw is false, so shouldn't be included
});
