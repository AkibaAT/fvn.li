<?php

declare(strict_types=1);

use App\Models\ClickStat;
use App\Models\Game;
use App\Models\User;
use App\Services\IpAnonymizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->game = Game::factory()->create();
});

describe('click statistics anonymization for GDPR', function () {
    test('anonymizes user_id and ip_address for user', function () {
        $clickStat = ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        $result = ClickStat::anonymizePersonalDataForUser($this->user->id);

        expect($result)->toBe(1);

        $clickStat->refresh();
        expect($clickStat->user_id)->toBeNull()
            ->and($clickStat->ip_address)->not->toBe('192.168.1.100')
            ->and(IpAnonymizationService::isAnonymized($clickStat->ip_address))->toBeTrue();
    });

    test('anonymizes multiple click stats for same user', function () {
        for ($i = 0; $i < 5; $i++) {
            ClickStat::create([
                'game_id' => $this->game->id,
                'user_id' => $this->user->id,
                'type' => ClickStat::TYPE_PAGE_VIEW,
                'session_id' => 'test-session-' . $i,
                'ip_address' => "192.168.1.{$i}",
                'clicked_at' => now(),
            ]);
        }

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $stats = ClickStat::where('game_id', $this->game->id)->get();

        foreach ($stats as $stat) {
            expect($stat->user_id)->toBeNull()
                ->and(IpAnonymizationService::isAnonymized($stat->ip_address))->toBeTrue();
        }

        // One erased person stays one person, so their rows still resolve to a
        // single visitor rather than merging with every other erased account.
        expect($stats->pluck('ip_address')->unique())->toHaveCount(1);
    });

    test('does not affect other users click stats', function () {
        $otherUser = User::factory()->create();

        ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-1',
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $otherUser->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-2',
            'ip_address' => '192.168.1.200',
            'clicked_at' => now(),
        ]);

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $otherUserStat = ClickStat::where('user_id', $otherUser->id)->first();
        expect($otherUserStat->user_id)->toBe($otherUser->id)
            ->and($otherUserStat->ip_address)->toBe('192.168.1.200');
    });

    test('preserves game_id and type during anonymization', function () {
        $clickStat = ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_EXTERNAL_PROJECT,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $clickStat->refresh();
        expect($clickStat->game_id)->toBe($this->game->id)
            ->and($clickStat->type)->toBe(ClickStat::TYPE_EXTERNAL_PROJECT);
    });

    test('preserves timestamp during anonymization', function () {
        $timestamp = now()->subDays(5);

        $clickStat = ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'clicked_at' => $timestamp,
        ]);

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $clickStat->refresh();
        expect($clickStat->clicked_at->timestamp)->toBe($timestamp->timestamp);
    });

    test('updates updated_at timestamp during anonymization', function () {
        $clickStat = ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        $originalUpdatedAt = $clickStat->updated_at;

        sleep(1);

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $clickStat->refresh();
        expect($clickStat->updated_at->timestamp)->toBeGreaterThan($originalUpdatedAt->timestamp);
    });
});

describe('click statistics export for GDPR', function () {
    function connectItchio(User $user, string $username = 'testuser'): void
    {
        $user->socialAccounts()->create([
            'provider_name' => 'itchio',
            'provider_id' => '12345',
            'provider_data' => [
                'username' => $username,
                'url' => "https://{$username}.itch.io",
            ],
        ]);
    }

    function ownedGame(string $name, string $slug): Game
    {
        return Game::factory()->create([
            'name' => $name,
            'platform' => 'itch_io',
            'is_visible' => true,
            'url' => ['itch_io' => "https://testuser.itch.io/{$slug}"],
        ]);
    }

    function recordVisit(Game $game, string $type, ?string $botReason = null, ?int $visitorId = null): void
    {
        ClickStat::create([
            'game_id' => $game->id,
            'user_id' => $visitorId,
            'type' => $type,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'referrer' => 'https://www.google.com/',
            'bot_reason' => $botReason,
            'clicked_at' => now(),
        ]);
    }

    test('reports per game totals for owned games', function () {
        $ownedGame = ownedGame('My Game', 'my-game');

        connectItchio($this->user);

        recordVisit($ownedGame, ClickStat::TYPE_PAGE_VIEW);
        recordVisit($ownedGame, ClickStat::TYPE_PAGE_VIEW);
        recordVisit($ownedGame, ClickStat::TYPE_EXTERNAL_PROJECT);

        $export = ClickStat::exportUserOwnedGameStats($this->user->id);

        expect($export['total_entries'])->toBe(3);

        $game = collect($export['games_tracked'])->firstWhere('game_name', 'My Game');

        expect($game['total_clicks'])->toBe(3)
            ->and($game['page_views'])->toBe(2)
            ->and($game['external_project_clicks'])->toBe(1)
            ->and($game['custom_link_clicks'])->toBe(0)
            ->and($game['first_tracked'])->not->toBeNull();
    });

    test('carries no per visitor event detail', function () {
        $ownedGame = ownedGame('My Game', 'my-game');

        connectItchio($this->user);

        $visitor = User::factory()->create();
        recordVisit($ownedGame, ClickStat::TYPE_PAGE_VIEW, visitorId: $visitor->id);

        $export = ClickStat::exportUserOwnedGameStats($this->user->id);

        // A visitor's browsing is their personal data, not the game owner's.
        expect($export)->not->toHaveKey('detailed_logs');

        // The payload is a closed set of aggregate keys, so no visitor-level
        // field can arrive by being added upstream.
        expect(array_keys($export))->toBe(['user_id', 'exported_at', 'total_entries', 'games_tracked'])
            ->and(array_keys($export['games_tracked'][0]))->toBe([
                'game_name', 'game_url', 'total_clicks', 'page_views',
                'external_project_clicks', 'custom_link_clicks', 'first_tracked', 'last_tracked',
            ]);

        $encoded = json_encode($export);

        expect($encoded)->not->toContain('google.com')
            ->and($encoded)->not->toContain('192.168.1.100')
            ->and($encoded)->not->toContain('test-session-')
            ->and($encoded)->not->toContain($visitor->email);
    });

    test('leaves automated traffic out of the totals', function () {
        $ownedGame = ownedGame('My Game', 'my-game');

        connectItchio($this->user);

        recordVisit($ownedGame, ClickStat::TYPE_PAGE_VIEW);

        foreach (range(1, 5) as $ignored) {
            recordVisit($ownedGame, ClickStat::TYPE_PAGE_VIEW, botReason: 'blocked_network');
        }

        $export = ClickStat::exportUserOwnedGameStats($this->user->id);
        $game = collect($export['games_tracked'])->firstWhere('game_name', 'My Game');

        expect($export['total_entries'])->toBe(1)
            ->and($game['page_views'])->toBe(1);
    });

    test('does not export stats for games not owned by user', function () {
        $otherGame = Game::factory()->create([
            'name' => 'Other Game',
            'platform' => 'itch_io',
            'is_visible' => true,
            'url' => ['itch_io' => 'https://otheruser.itch.io/other-game'],
        ]);

        connectItchio($this->user);

        recordVisit($otherGame, ClickStat::TYPE_PAGE_VIEW);

        $export = ClickStat::exportUserOwnedGameStats($this->user->id);

        expect(collect($export['games_tracked'] ?? [])->pluck('game_name'))
            ->not->toContain($otherGame->name);
    });

    test('reports a game with no traffic as zero rather than omitting it', function () {
        ownedGame('Quiet Game', 'quiet-game');

        connectItchio($this->user);

        $export = ClickStat::exportUserOwnedGameStats($this->user->id);
        $game = collect($export['games_tracked'])->firstWhere('game_name', 'Quiet Game');

        expect($game['total_clicks'])->toBe(0)
            ->and($game['first_tracked'])->toBeNull()
            ->and($game['last_tracked'])->toBeNull();
    });
});

describe('IP anonymization in click stats', function () {
    test('does not link an erased visitor across games', function () {
        $otherGame = Game::factory()->create();

        foreach ([$this->game, $otherGame] as $game) {
            ClickStat::create([
                'game_id' => $game->id,
                'user_id' => $this->user->id,
                'type' => ClickStat::TYPE_PAGE_VIEW,
                'session_id' => 'test-session-' . $game->id,
                'ip_address' => '192.168.1.100',
                'clicked_at' => now(),
            ]);
        }

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        expect(ClickStat::whereIn('game_id', [$this->game->id, $otherGame->id])->pluck('ip_address')->unique())
            ->toHaveCount(2);
    });

    test('gives two erased accounts distinct pseudonyms', function () {
        $otherUser = User::factory()->create();

        foreach ([$this->user, $otherUser] as $visitor) {
            ClickStat::create([
                'game_id' => $this->game->id,
                'user_id' => $visitor->id,
                'type' => ClickStat::TYPE_PAGE_VIEW,
                'session_id' => 'test-session-' . $visitor->id,
                'ip_address' => '192.168.1.100',
                'clicked_at' => now(),
            ]);
        }

        ClickStat::anonymizePersonalDataForUser($this->user->id);
        ClickStat::anonymizePersonalDataForUser($otherUser->id);

        expect(ClickStat::where('game_id', $this->game->id)->pluck('ip_address')->unique())
            ->toHaveCount(2);
    });

    test('reports how many rows it anonymised', function () {
        expect(ClickStat::anonymizePersonalDataForUser($this->user->id))->toBe(0);

        ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        expect(ClickStat::anonymizePersonalDataForUser($this->user->id))->toBe(1);
    });

    test('handles null IP addresses during anonymization', function () {
        $clickStat = ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => null,
            'clicked_at' => now(),
        ]);

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $clickStat->refresh();
        expect(IpAnonymizationService::isAnonymized($clickStat->ip_address))->toBeTrue();
    });
});

describe('analytics preservation after anonymization', function () {
    test('preserves click type statistics after anonymization', function () {
        ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-1',
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_EXTERNAL_PROJECT,
            'session_id' => 'test-session-2',
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $pageViews = ClickStat::where('game_id', $this->game->id)
            ->where('type', ClickStat::TYPE_PAGE_VIEW)
            ->count();

        $externalClicks = ClickStat::where('game_id', $this->game->id)
            ->where('type', ClickStat::TYPE_EXTERNAL_PROJECT)
            ->count();

        expect($pageViews)->toBe(1)
            ->and($externalClicks)->toBe(1);
    });

    test('preserves temporal data for analytics', function () {
        $dates = [
            now()->subDays(7),
            now()->subDays(3),
            now(),
        ];

        foreach ($dates as $index => $date) {
            ClickStat::create([
                'game_id' => $this->game->id,
                'user_id' => $this->user->id,
                'type' => ClickStat::TYPE_PAGE_VIEW,
                'session_id' => 'test-session-' . $index,
                'ip_address' => '192.168.1.100',
                'clicked_at' => $date,
            ]);
        }

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $stats = ClickStat::where('game_id', $this->game->id)
            ->orderBy('clicked_at')
            ->get();

        expect($stats)->toHaveCount(3)
            ->and($stats[0]->clicked_at->timestamp)->toBe($dates[0]->timestamp)
            ->and($stats[2]->clicked_at->timestamp)->toBe($dates[2]->timestamp);
    });
});

describe('edge cases and error handling', function () {
    test('handles anonymization of already anonymized data', function () {
        $clickStat = ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        // Anonymize once
        ClickStat::anonymizePersonalDataForUser($this->user->id);
        $clickStat->refresh();

        // After anonymization, user_id is gone and the address is unrecoverable
        expect($clickStat->user_id)->toBeNull()
            ->and($clickStat->ip_address)->not->toBe('192.168.1.100')
            ->and(IpAnonymizationService::isAnonymized($clickStat->ip_address))->toBeTrue();

        $pseudonym = $clickStat->ip_address;

        // Anonymizing again should be idempotent (no error, no change since user_id is already null)
        ClickStat::anonymizePersonalDataForUser($this->user->id);
        $clickStat->refresh();

        // Should remain the same since there are no more records for this user
        expect($clickStat->ip_address)->toBe($pseudonym);
    });
});
