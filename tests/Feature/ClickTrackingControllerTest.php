<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;

function trackedGame(array $attributes = []): Game
{
    return Game::factory()->create(array_merge([
        'name' => 'Tracked Game',
        'platform' => 'itch_io',
        'url' => ['itch_io' => 'https://studiofox.itch.io/tracked-game'],
        'additional_links' => [
            [
                'id' => 'windows',
                'name' => 'Windows',
                'url' => 'https://downloads.example.com/windows.zip',
                'sort_order' => 1,
            ],
        ],
    ], $attributes));
}

function connectItchioAccount(User $user, string $username = 'studiofox'): void
{
    SocialAccount::create([
        'user_id' => $user->id,
        'provider_name' => 'itchio',
        'provider_id' => $username,
        'provider_data' => [
            'username' => $username,
            'url' => "https://{$username}.itch.io",
        ],
    ]);
}

it('redirects external project clicks and records them', function () {
    $user = User::factory()->create();
    $game = trackedGame();

    $this->actingAs($user)
        ->withHeader('referer', 'https://fvn.li/games/tracked-game')
        ->withHeader('User-Agent', 'Feature Browser')
        ->get(route('track.external-project', [
            'game_id' => $game->id,
            'url' => 'https://studiofox.itch.io/tracked-game',
        ]))
        ->assertRedirect('https://studiofox.itch.io/tracked-game')
        ->assertHeader('Referrer-Policy', 'origin');

    $click = ClickStat::where('game_id', $game->id)
        ->where('type', ClickStat::TYPE_EXTERNAL_PROJECT)
        ->firstOrFail();

    expect($click->user_id)->toBe($user->id)
        ->and($click->link_id)->toBeNull()
        ->and($click->referrer)->toBe('https://fvn.li/games/tracked-game')
        ->and($click->user_agent)->toBe('Feature Browser')
        ->and($click->ip_address)->not->toBeNull();
});

it('falls back to the local game page when an external project URL is missing', function () {
    $game = trackedGame([
        'platform' => 'other',
        'url' => [],
    ]);

    $this->get(route('track.external-project', [
        'game_id' => $game->id,
    ]))->assertRedirect(route('games.show', $game->slug));

    expect(ClickStat::where('game_id', $game->id)->exists())->toBeFalse();
});

it('redirects custom links only when the link exists', function () {
    $game = trackedGame();

    $this->get(route('track.custom-link', [
        'game_id' => $game->id,
        'link_id' => 'windows',
    ]))->assertRedirect('https://downloads.example.com/windows.zip')
        ->assertHeader('Referrer-Policy', 'origin');

    expect(ClickStat::where('game_id', $game->id)
        ->where('type', ClickStat::TYPE_CUSTOM_LINK)
        ->where('link_id', 'windows')
        ->exists())->toBeTrue();

    $this->get(route('track.custom-link', [
        'game_id' => $game->id,
        'link_id' => 'linux',
    ]))->assertRedirect(route('games.show', $game->slug));
});

it('tracks custom links through the JSON endpoint', function () {
    $game = trackedGame();

    $this->postJson(route('browser-api.track.custom-link'), [
        'game_id' => $game->id,
        'link_id' => 'windows',
        'url' => 'https://downloads.example.com/windows.zip',
    ])
        ->assertOk()
        ->assertJson([
            'success' => true,
            'recorded' => true,
            'redirect_url' => 'https://downloads.example.com/windows.zip',
        ]);

    $this->postJson(route('browser-api.track.custom-link'), [
        'game_id' => $game->id,
        'link_id' => 'missing',
        'url' => 'https://downloads.example.com/missing.zip',
    ])
        ->assertNotFound()
        ->assertJson([
            'success' => false,
            'message' => 'Link not found for this game',
        ]);

    $this->postJson(route('browser-api.track.custom-link'), [
        'game_id' => $game->id,
        'link_id' => 'windows',
        'url' => 'not-a-url',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('success', false)
        ->assertJsonValidationErrors(['url']);
});

it('returns aggregate and daily analytics only to the owning developer', function () {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();
    $game = trackedGame();
    connectItchioAccount($owner, 'studiofox');

    ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_PAGE_VIEW,
        'session_id' => 'session-a',
        'ip_address' => 'hash-a',
        'user_agent' => 'Browser A',
        'clicked_at' => now()->subDay(),
    ]);
    ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_EXTERNAL_PROJECT,
        'session_id' => 'session-b',
        'ip_address' => 'hash-b',
        'user_agent' => 'Browser B',
        'clicked_at' => now()->subDay(),
    ]);
    ClickStat::create([
        'game_id' => $game->id,
        'type' => ClickStat::TYPE_CUSTOM_LINK,
        'link_id' => 'windows',
        'session_id' => 'session-c',
        'ip_address' => 'hash-c',
        'user_agent' => 'Browser C',
        'clicked_at' => now()->subDay(),
    ]);

    $this->actingAs($otherUser)
        ->getJson(route('api.games.stats', $game))
        ->assertForbidden()
        ->assertJson([
            'success' => false,
            'message' => 'You do not have permission to view stats for this game',
        ]);

    $this->actingAs($owner)
        ->getJson(route('api.games.stats', [$game, 'days' => 7]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('period_days', 7)
        ->assertJsonPath('stats.page_views_total', 1)
        ->assertJsonPath('stats.external_project_total', 1)
        ->assertJsonPath('stats.custom_links.windows.total_clicks', 1)
        ->assertJsonPath('stats.custom_links.windows.unique_clicks', 1);

    $this->actingAs($owner)
        ->getJson(route('api.games.analytics', [$game, 'days' => 7]))
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('period_days', 7)
        ->assertJsonPath('link_stats.0.link_id', 'windows')
        ->assertJsonPath('link_stats.0.total_clicks', 1)
        ->assertJsonPath('link_stats.0.unique_clicks', 1);
});

it('aggregates multiple game stats and empty link stats', function () {
    $firstGame = trackedGame();
    $secondGame = trackedGame([
        'name' => 'Second Tracked Game',
        'url' => ['itch_io' => 'https://studiofox.itch.io/second-game'],
        'additional_links' => [],
    ]);

    ClickStat::create([
        'game_id' => $firstGame->id,
        'type' => ClickStat::TYPE_CUSTOM_LINK,
        'link_id' => 'windows',
        'session_id' => 'session-a',
        'ip_address' => 'hash-a',
        'user_agent' => 'Browser A',
        'clicked_at' => now()->subDay(),
    ]);
    ClickStat::create([
        'game_id' => $secondGame->id,
        'type' => ClickStat::TYPE_EXTERNAL_PROJECT,
        'session_id' => 'session-b',
        'ip_address' => 'hash-b',
        'user_agent' => 'Browser B',
        'clicked_at' => now()->subDay(),
    ]);

    $stats = ClickStat::getMultipleGameStats([$firstGame->id, $secondGame->id], now()->subDays(7));

    expect($stats[$firstGame->id]['custom_link_clicks_total'])->toBe(1)
        ->and($stats[$firstGame->id]['custom_link_clicks_unique'])->toBe(1)
        ->and($stats[$secondGame->id]['external_project_total'])->toBe(1)
        ->and(ClickStat::getLinkStats($secondGame->id))->toBe([]);
});
