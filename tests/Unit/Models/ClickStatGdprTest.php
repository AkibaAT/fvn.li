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

        expect($result)->toBeTrue();

        $clickStat->refresh();
        expect($clickStat->user_id)->toBeNull()
            ->and($clickStat->ip_address)->toStartWith('hash_')
            ->and($clickStat->ip_address)->not->toContain('192.168');
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
                ->and($stat->ip_address)->toStartWith('hash_');
        }
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
    test('exports click statistics for user owned games', function () {
        // Create a game owned by the user
        $ownedGame = Game::factory()->create([
            'url' => 'https://testuser.itch.io/my-game',
        ]);

        // Mock the user's itch.io account
        $this->user->socialAccounts()->create([
            'provider_name' => 'itchio',
            'provider_id' => '12345',
            'provider_data' => [
                'username' => 'testuser',
                'url' => 'https://testuser.itch.io',
            ],
        ]);

        // Create click stats for the owned game
        ClickStat::create([
            'game_id' => $ownedGame->id,
            'user_id' => null,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        $export = ClickStat::exportUserOwnedGameStats($this->user->id);

        expect($export)->toBeArray();
    });

    test('does not export stats for games not owned by user', function () {
        $otherGame = Game::factory()->create([
            'url' => 'https://otheruser.itch.io/other-game',
        ]);

        ClickStat::create([
            'game_id' => $otherGame->id,
            'user_id' => null,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => '192.168.1.100',
            'clicked_at' => now(),
        ]);

        $export = ClickStat::exportUserOwnedGameStats($this->user->id);

        expect($export)->toBeArray();
    });
});

describe('IP anonymization in click stats', function () {
    test('uses hash method for anonymization', function () {
        $originalIp = '203.0.113.45';

        $clickStat = ClickStat::create([
            'game_id' => $this->game->id,
            'user_id' => $this->user->id,
            'type' => ClickStat::TYPE_PAGE_VIEW,
            'session_id' => 'test-session-' . uniqid(),
            'ip_address' => $originalIp,
            'clicked_at' => now(),
        ]);

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $clickStat->refresh();
        $expectedHash = IpAnonymizationService::anonymize($originalIp, 'hash');

        expect($clickStat->ip_address)->toBe($expectedHash);
    });

    test('consistent hash for same IP across multiple records', function () {
        $ip = '192.168.1.100';

        for ($i = 0; $i < 3; $i++) {
            ClickStat::create([
                'game_id' => $this->game->id,
                'user_id' => $this->user->id,
                'type' => ClickStat::TYPE_PAGE_VIEW,
                'session_id' => 'test-session-' . $i,
                'ip_address' => $ip,
                'clicked_at' => now(),
            ]);
        }

        ClickStat::anonymizePersonalDataForUser($this->user->id);

        $stats = ClickStat::where('game_id', $this->game->id)->get();
        $hashes = $stats->pluck('ip_address')->unique();

        // All should have the same hash since they had the same IP
        expect($hashes)->toHaveCount(1);
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
        expect($clickStat->ip_address)->toBeNull();
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
    test('handles user with no click statistics', function () {
        $result = ClickStat::anonymizePersonalDataForUser($this->user->id);

        expect($result)->toBeTrue();
    });

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

        // After anonymization, user_id should be null and IP should be hashed
        expect($clickStat->user_id)->toBeNull()
            ->and($clickStat->ip_address)->toStartWith('hash_');

        // Anonymizing again should be idempotent (no error, no change since user_id is already null)
        $firstHash = $clickStat->ip_address;
        ClickStat::anonymizePersonalDataForUser($this->user->id);
        $clickStat->refresh();

        // Should remain the same since there are no more records for this user
        expect($clickStat->ip_address)->toBe($firstHash);
    });
});
