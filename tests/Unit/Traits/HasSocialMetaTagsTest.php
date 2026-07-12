<?php

declare(strict_types=1);

use App\Models\Game;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Tests\Support\HeadingMetaTagsHarness;
use Tests\Support\NoGamesMetaTagsHarness;
use Tests\Support\SocialMetaTagsHarness;

function socialMetaPaginator(Collection $games, int $total): LengthAwarePaginator
{
    return new LengthAwarePaginator(
        $games,
        $total,
        20,
        1,
        ['path' => '/games'],
    );
}

it('stores explicit social meta tags and returns configured fallback image', function () {
    config(['social.images.default' => 'images/social-default.png']);
    $harness = new SocialMetaTagsHarness;

    $harness->setMetaTags(['title' => 'First', 'description' => 'Description']);
    $harness->setMetaTags(['title' => 'Second']);

    expect($harness->getMetaTags())->toBe([
        'title' => 'Second',
        'description' => 'Description',
    ])
        ->and($harness->image())->toContain('/images/social-default.png');
});

it('builds filtered game list meta titles and descriptions', function () {
    $harness = new SocialMetaTagsHarness;
    $harness->games = socialMetaPaginator(collect([
        new Game(['name' => 'First VN']),
        new Game(['name' => 'Second VN']),
        new Game(['name' => 'Third VN']),
        new Game(['name' => 'Fourth VN']),
        new Game(['name' => 'Fifth VN']),
    ]), 42);
    $harness->selectedStatuses = ['Completed'];
    $harness->selectedEngines = ['RenPy'];
    $harness->selectedPlatforms = ['windows', 'linux'];
    $harness->nsfw = true;
    $harness->search = 'wolves';
    $harness->sortField = 'rating_score';
    $harness->sortDirection = 'asc';

    expect($harness->title())->toBe("42 FVNs that are completed, made with RenPy, for Windows and for Linux, NSFW, matching 'wolves'")
        ->and($harness->description())->toBe("Browse NSFW Windows/Linux FVNs that are completed and created with RenPy matching 'wolves'. Featuring 42 titles, including: First VN, Second VN, Third VN, Fourth VN, sorted by Rating ascending.")
        ->and($harness->activeFilters())->toBeTrue();
});

it('builds simple fallback title and description variants', function () {
    config(['app.description' => 'Configured app description']);
    $fallbackHarness = new NoGamesMetaTagsHarness;
    $harness = new SocialMetaTagsHarness;

    expect($fallbackHarness->description())->toBe('Configured app description')
        ->and($harness->activeFilters())->toBeFalse();

    $harness->games = socialMetaPaginator(collect(), 0);
    $harness->sfw = true;

    expect($harness->title())->toBe('0 FVNs')
        ->and($harness->description())->toBe('Browse SFW FVNs. Featuring 0 titles.')
        ->and($harness->activeFilters())->toBeTrue();
});

it('builds table heading meta titles with singular pluralization', function () {
    $harness = new HeadingMetaTagsHarness;

    expect($harness->title())->toBe('1 game');
});
