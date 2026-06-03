<?php

declare(strict_types=1);

use App\Models\Tag;
use App\Models\User;
use App\Models\UserPreference;
use App\Services\MeilisearchService;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Testing\AssertableInertia as Assert;

function fakeEmptyGameSearch(): void
{
    app()->instance(
        MeilisearchService::class,
        Mockery::mock(MeilisearchService::class, function ($mock) {
            $mock->shouldReceive('searchGames')
                ->andReturnUsing(fn () => new LengthAwarePaginator([], 0, 8, 1));
        })
    );
}

test('games search applies profile default filters when no explicit override is present', function () {
    fakeEmptyGameSearch();

    $user = User::factory()->create();
    $tag = Tag::create(['name' => 'AI Generated']);
    UserPreference::create([
        'user_id' => $user->id,
        'preferred_languages' => ['eng'],
        'excluded_tags' => [$tag->id],
    ]);

    $this->actingAs($user)
        ->get(route('games.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('games/index')
            ->where('currentFilters.selectedLanguages', ['eng'])
            ->where('currentFilters.excludedTags', [(string) $tag->id])
            ->where('currentFilters.usingDefaultLanguages', true)
            ->where('currentFilters.usingDefaultExcludedTags', true)
        );
});

test('games search does not reapply profile defaults when filters are explicitly cleared', function () {
    fakeEmptyGameSearch();

    $user = User::factory()->create();
    $tag = Tag::create(['name' => 'AI Generated']);
    UserPreference::create([
        'user_id' => $user->id,
        'preferred_languages' => ['eng'],
        'excluded_tags' => [$tag->id],
    ]);

    $this->actingAs($user)
        ->get(route('games.index', ['noDefaults' => true]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('games/index')
            ->where('currentFilters.selectedLanguages', [])
            ->where('currentFilters.excludedTags', [])
            ->where('currentFilters.noDefaults', true)
            ->where('currentFilters.usingDefaultLanguages', false)
            ->where('currentFilters.usingDefaultExcludedTags', false)
        );
});
