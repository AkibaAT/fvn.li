<?php

use App\Models\Game;
use App\Models\GameVersion;

test('new games feed includes only visible public games ordered by first visibility', function () {
    $newest = Game::factory()->create([
        'name' => 'Newest Feed VN',
        'slug' => 'newest-feed-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
        'first_visible_at' => now(),
    ]);
    $older = Game::factory()->create([
        'name' => 'Older Feed VN',
        'slug' => 'older-feed-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
        'first_visible_at' => now()->subDay(),
    ]);
    Game::factory()->create([
        'name' => 'Hidden Feed VN',
        'slug' => 'hidden-feed-vn',
        'is_visible' => false,
        'content_type' => 'visual_novel',
        'first_visible_at' => now()->addDay(),
    ]);
    Game::factory()->create([
        'name' => 'Adjacent Feed Game',
        'slug' => 'adjacent-feed-game',
        'is_visible' => true,
        'content_type' => 'adjacent',
        'first_visible_at' => now()->addDay(),
    ]);

    $response = $this->get(route('feed.new'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
        ->assertSee('FVN.li - New Visual Novels', false)
        ->assertSee($newest->name, false)
        ->assertSee($older->name, false)
        ->assertDontSee('Hidden Feed VN', false)
        ->assertDontSee('Adjacent Feed Game', false);

    expect($response->getContent())->toContain($newest->slug)
        ->and(strpos($response->getContent(), $newest->name))->toBeLessThan(strpos($response->getContent(), $older->name));
});

test('updated games feed includes games with latest versions ordered by published date', function () {
    $freshGame = Game::factory()->create([
        'name' => 'Fresh Updated VN',
        'slug' => 'fresh-updated-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
    ]);
    $oldGame = Game::factory()->create([
        'name' => 'Old Updated VN',
        'slug' => 'old-updated-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
    ]);
    $noVersion = Game::factory()->create([
        'name' => 'No Version VN',
        'slug' => 'no-version-vn',
        'is_visible' => true,
        'content_type' => 'visual_novel',
    ]);

    GameVersion::factory()->latest()->create([
        'game_id' => $freshGame->id,
        'published_at' => now(),
    ]);
    GameVersion::factory()->latest()->create([
        'game_id' => $oldGame->id,
        'published_at' => now()->subWeek(),
    ]);

    $response = $this->get(route('feed.updates'));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=utf-8')
        ->assertSee('FVN.li - Updated Visual Novels', false)
        ->assertSee($freshGame->name, false)
        ->assertSee($oldGame->name, false)
        ->assertDontSee($noVersion->name, false);

    expect(strpos($response->getContent(), $freshGame->name))->toBeLessThan(strpos($response->getContent(), $oldGame->name));
});
