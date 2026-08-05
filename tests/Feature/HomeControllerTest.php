<?php

declare(strict_types=1);

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\User;
use App\Models\VersionLanguageStats;
use App\Models\VersionSupportedLanguage;
use App\Models\VnList;
use App\Models\VnListEntry;
use App\Services\MeilisearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

describe('Home Page Game Cards', function () {
    test('home teasers enrich meilisearch results with relationships and authenticated user data', function () {
        Cache::flush();

        DB::table('iso_639_3_languages')->insertOrIgnore([
            'id' => 'eng',
            'scope' => 'I',
            'type' => 'L',
            'ref_name' => 'English',
            'part1' => 'en',
            'flag_code' => 'gb',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create();
        $game = Game::factory()->create([
            'name' => 'Mocked Search Home VN',
            'slug' => 'mocked-search-home-vn',
            'is_visible' => true,
            'source_language_id' => 'eng',
        ]);
        $ignoredGame = Game::factory()->create();
        $user->ignoredGames()->attach($ignoredGame->id);

        $version = GameVersion::factory()->latest()->create([
            'game_id' => $game->id,
            'is_windows' => true,
            'is_linux' => false,
            'is_mac' => true,
            'is_android' => false,
            'is_web' => true,
            'published_at' => now(),
        ]);

        VersionSupportedLanguage::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'is_available' => true,
        ]);
        VersionLanguageStats::create([
            'game_version_id' => $version->id,
            'iso_code' => 'eng',
            'words' => 12345,
        ]);

        DB::table('user_game_progress')->insert([
            'user_id' => $user->id,
            'game_id' => $game->id,
            'status' => 'reading',
            'receive_updates' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $list = VnList::factory()->create([
            'user_id' => $user->id,
            'name' => 'Home List',
            'type' => 'custom',
            'is_default' => false,
        ]);
        VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
        ]);

        $search = Mockery::mock(MeilisearchService::class);
        $search
            ->shouldReceive('searchGames')
            ->times(3)
            ->withArgs(function (
                string $query,
                array $filters,
                int $perPage,
                int $page,
                string $sortField,
                string $sortDirection,
                array $ignoredGameIds
            ) use ($ignoredGame) {
                expect($query)->toBe('')
                    ->and($filters)->toBe([])
                    ->and($perPage)->toBe(4)
                    ->and($page)->toBe(1)
                    ->and($sortDirection)->toBe('desc')
                    ->and($sortField)->toBeIn(['first_visible_at', 'latest_version_published_at', 'trending_score'])
                    ->and($ignoredGameIds)->toBe([$ignoredGame->id]);

                return true;
            })
            ->andReturnUsing(fn () => new LengthAwarePaginator($game->newCollection([$game->fresh()]), 1, 4, 1));

        app()->instance(MeilisearchService::class, $search);

        $response = $this->actingAs($user)->get(route('home'));

        $response->assertOk();
        $props = $response->viewData('page')['props'];
        $firstGame = $props['teasers']['recentlyAdded'][0];

        expect($props['ignoredGameIds'])->toBe([$ignoredGame->id])
            ->and($firstGame['id'])->toBe($game->id)
            ->and($firstGame['is_windows'])->toBeTrue()
            ->and($firstGame['is_mac'])->toBeTrue()
            ->and($firstGame['is_web'])->toBeTrue()
            ->and($firstGame['latest_version_id'])->toBe($version->id)
            ->and($firstGame['english_word_count'])->toBe(12345)
            ->and($firstGame['primary_word_count'])->toBe(12345)
            ->and($firstGame['primary_language_label'])->toBe('EN')
            ->and($firstGame['supported_languages'][0]['iso_code'])->toBe('eng')
            ->and($firstGame['supported_languages'][0]['ref_name'])->toBe('English')
            ->and($firstGame['user_progress'][0]->receive_updates)->toBeTrue()
            ->and($firstGame['user_list_memberships'][0]->name)->toBe('Home List');
    });

    test('home teaser cache does not leak authenticated user data to later guests', function () {
        Cache::flush();

        $victim = User::factory()->create();
        $game = Game::factory()->create([
            'name' => 'Cached Teaser Privacy VN',
            'slug' => 'cached-teaser-privacy-vn',
            'is_visible' => true,
        ]);

        DB::table('user_game_progress')->insert([
            'user_id' => $victim->id,
            'game_id' => $game->id,
            'status' => 'reading',
            'receive_updates' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $list = VnList::factory()->create([
            'user_id' => $victim->id,
            'name' => 'Victim Private Wishlist',
            'type' => 'custom',
            'is_default' => false,
        ]);
        VnListEntry::factory()->create([
            'vn_list_id' => $list->id,
            'game_id' => $game->id,
        ]);

        $search = Mockery::mock(MeilisearchService::class);
        $search
            ->shouldReceive('searchGames')
            ->times(3)
            ->andReturnUsing(fn () => new LengthAwarePaginator($game->newCollection([$game->fresh()]), 1, 4, 1));

        app()->instance(MeilisearchService::class, $search);

        $victimResponse = $this->actingAs($victim)->get(route('home'));
        $victimResponse->assertOk();
        $victimGame = $victimResponse->viewData('page')['props']['teasers']['recentlyAdded'][0];

        Auth::logout();

        $guestResponse = $this->get(route('home'));
        $guestResponse->assertOk();
        $guestGame = $guestResponse->viewData('page')['props']['teasers']['recentlyAdded'][0];

        expect($victimGame['user_progress'][0]->receive_updates)->toBeTrue()
            ->and($victimGame['user_list_memberships'][0]->name)->toBe('Victim Private Wishlist')
            ->and($guestGame['id'])->toBe($game->id)
            ->and($guestGame['user_progress'])->toBe([])
            ->and($guestGame['user_list_memberships'])->toBe([]);
    });
});
