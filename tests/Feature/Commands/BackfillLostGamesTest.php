<?php

declare(strict_types=1);

use App\Models\ChangeLog;
use App\Models\Game;
use App\Services\ItchHttpClientService;
use App\Support\DelistedReason;
use GuzzleHttp\Psr7\Response;

function hiddenGame(string $slug, array $attributes = []): Game
{
    return Game::factory()->create([
        'platform' => 'itch_io',
        'url' => ['itch_io' => "https://creator.itch.io/{$slug}"],
        'is_visible' => false,
        'first_visible_at' => '2025-01-01',
        ...$attributes,
    ]);
}

function logHide(Game $game, string $source, ?string $command): void
{
    ChangeLog::create([
        'timestamp' => '2026-04-21 03:00:00',
        'event_type' => ChangeLog::EVENT_UPDATED,
        'entity_type' => Game::class,
        'entity_id' => $game->id,
        'old_values' => ['is_visible' => true],
        'new_values' => ['is_visible' => false],
        'context' => $command ? ['command' => ['name' => $command]] : [],
        'source' => $source,
    ]);
}

function storePages(array $pages): void
{
    $client = Mockery::mock(ItchHttpClientService::class);
    foreach ($pages as $slug => $response) {
        $client->shouldReceive('get')->once()->with("https://creator.itch.io/{$slug}", [], true)->andReturn($response);
    }
    app()->instance(ItchHttpClientService::class, $client);
}

it('re-lists games hidden by the refresh command as lost since they were hidden', function () {
    $removed = hiddenGame('removed');
    $locked = hiddenGame('locked');
    $available = hiddenGame('available');
    $byAdmin = hiddenGame('by-admin');
    $moderated = hiddenGame('moderated', ['is_moderation_hidden' => true]);
    foreach ([$removed, $locked, $available, $moderated] as $game) {
        logHide($game, ChangeLog::SOURCE_COMMAND, 'games:refresh');
    }
    logHide($byAdmin, ChangeLog::SOURCE_WEB, null);
    storePages([
        'removed' => new Response(404),
        'locked' => new Response(200, [], '<div class="game_password_page"></div>'),
        'available' => new Response(200, [], '<div class="view_game_page"></div>'),
    ]);

    $this->artisan('games:backfill-lost')->assertSuccessful();

    expect($removed->refresh())
        ->is_visible->toBeTrue()
        ->delisted_reason->toBe(DelistedReason::PageRemoved)
        ->and($removed->delisted_at->toDateTimeString())->toBe('2026-04-21 03:00:00')
        ->and($locked->refresh()->delisted_reason)->toBe(DelistedReason::PasswordProtected)
        ->and($available->refresh()->is_visible)->toBeFalse()
        ->and($byAdmin->refresh()->is_visible)->toBeFalse()
        ->and($moderated->refresh()->is_visible)->toBeFalse();
});

it('writes nothing on a dry run', function () {
    $removed = hiddenGame('removed');
    logHide($removed, ChangeLog::SOURCE_COMMAND, 'games:refresh');
    storePages(['removed' => new Response(404)]);

    $this->artisan('games:backfill-lost', ['--dry-run' => true])->assertSuccessful();

    expect($removed->refresh())->is_visible->toBeFalse()->delisted_at->toBeNull();
});
