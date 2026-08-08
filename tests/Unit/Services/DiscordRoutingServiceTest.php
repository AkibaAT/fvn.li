<?php

declare(strict_types=1);

use App\Models\DiscordServer;
use App\Models\DiscordServerGameOverride;
use App\Models\Game;
use App\Services\Discord\DiscordRoutingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Game::unsetEventDispatcher();

    $this->service = new DiscordRoutingService;

    $this->server = DiscordServer::factory()->create();
    $this->server->update([
        'available_channels' => [
            ['id' => '111111111', 'name' => 'general', 'type' => 0, 'nsfw' => false],
            ['id' => '222222222', 'name' => 'nsfw-updates', 'type' => 0, 'nsfw' => true],
            ['id' => 'override_channel', 'name' => 'override', 'type' => 0, 'nsfw' => true],
            ['id' => 'paid_channel', 'name' => 'paid', 'type' => 0, 'nsfw' => false],
            ['id' => 'high_priority_channel', 'name' => 'priority-hi', 'type' => 0, 'nsfw' => false],
            ['id' => 'low_priority_channel', 'name' => 'priority-lo', 'type' => 0, 'nsfw' => false],
            ['id' => 'active_channel', 'name' => 'active', 'type' => 0, 'nsfw' => false],
            ['id' => 'romance_channel', 'name' => 'romance', 'type' => 0, 'nsfw' => false],
        ],
    ]);

    $this->game = Game::factory()->create([
        'status' => 'Released',
        'is_nsfw' => false,
        'is_paid' => true,
        'authors' => 'TestDev',
    ]);

    $this->server->config()->create([
        'discord_server_id' => $this->server->id,
        'notification_channel_id' => '111111111',
        'notification_format' => 'compact',
    ]);
});

describe('DiscordRoutingService', function () {
    test('falls back to default channel when no rules match', function () {
        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        expect($result->shouldSkip)->toBeFalse()
            ->and($result->hasChannels())->toBeTrue()
            ->and($result->getTargetChannels())->toHaveCount(1)
            ->and($result->getTargetChannels()[0]['channel_id'])->toBe('111111111');
    });

    test('skips notification when game is ignored via override', function () {
        DiscordServerGameOverride::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'is_ignored' => true,
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        expect($result->shouldSkip)->toBeTrue()
            ->and($result->hasChannels())->toBeFalse();
    });

    test('does not skip when override exists but is not ignored', function () {
        DiscordServerGameOverride::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'is_ignored' => false,
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        expect($result->shouldSkip)->toBeFalse()
            ->and($result->hasChannels())->toBeTrue();
    });

    test('routes to specified channel when rule matches', function () {
        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Route updates',
                    'enabled' => true,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'notification_type', 'operator' => 'equals', 'value' => 'update'],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => '222222222'],
                ],
            ],
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        expect($result->shouldSkip)->toBeFalse()
            ->and($result->getTargetChannels())->toHaveCount(1)
            ->and($result->getTargetChannels()[0]['channel_id'])->toBe('222222222');
    });

    test('skips when rule action is ignore', function () {
        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Ignore NSFW',
                    'enabled' => true,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'is_nsfw', 'operator' => 'equals', 'value' => true],
                    ],
                    'action' => ['type' => 'ignore'],
                ],
            ],
        ]);

        $nsfwGame = Game::factory()->create(['is_nsfw' => true]);
        $result = $this->service->evaluateRoutes($this->server, $nsfwGame, 'update');

        expect($result->shouldSkip)->toBeTrue();
    });

    test('does not match disabled rules', function () {
        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Disabled rule',
                    'enabled' => false,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'notification_type', 'operator' => 'equals', 'value' => 'update'],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => '222222222'],
                ],
            ],
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        expect($result->getTargetChannels())->toHaveCount(1)
            ->and($result->getTargetChannels()[0]['channel_id'])->toBe('111111111');
    });

    test('evaluates rules by priority order', function () {
        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Low priority',
                    'enabled' => true,
                    'priority' => 10,
                    'conditions' => [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'Released'],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => 'low_priority_channel'],
                ],
                [
                    'id' => 'r2',
                    'name' => 'High priority',
                    'enabled' => true,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'status', 'operator' => 'equals', 'value' => 'Released'],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => 'high_priority_channel'],
                ],
            ],
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        $channels = $result->getTargetChannels();
        $channelIds = array_column($channels, 'channel_id');
        expect($channelIds)->toContain('high_priority_channel')
            ->and($channelIds)->toContain('low_priority_channel');
    });

    test('uses game override channel when set', function () {
        DiscordServerGameOverride::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'is_ignored' => false,
            'channel_id' => 'override_channel',
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        $channelIds = array_column($result->getTargetChannels(), 'channel_id');
        expect($channelIds)->toContain('override_channel');
    });

    test('matches not_equals operator', function () {
        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Not abandoned',
                    'enabled' => true,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'status', 'operator' => 'not_equals', 'value' => 'Abandoned'],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => 'active_channel'],
                ],
            ],
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');
        expect($result->getTargetChannels()[0]['channel_id'])->toBe('active_channel');
    });

    test('matches contains operator for array fields', function () {
        $this->game->tags()->create(['name' => 'Romance', 'slug' => 'romance']);
        $this->game->tags()->create(['name' => 'Fantasy', 'slug' => 'fantasy']);

        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Has romance tag',
                    'enabled' => true,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'tags', 'operator' => 'contains', 'value' => 'romance'],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => 'romance_channel'],
                ],
            ],
        ]);

        $this->game->load('tags');
        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        expect($result->getTargetChannels()[0]['channel_id'])->toBe('romance_channel');
    });

    test('coerces scalar values for equals and in rules', function () {
        $this->server->config->update([
            'routing_rules' => [[
                'enabled' => true,
                'priority' => 1,
                'conditions' => [
                    ['field' => 'is_paid', 'operator' => 'equals', 'value' => '1'],
                    ['field' => 'notification_type', 'operator' => 'in', 'value' => [1, 'update']],
                ],
                'action' => ['type' => 'route', 'channel_id' => 'paid_channel'],
            ]],
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');
        expect(array_column($result->getTargetChannels(), 'channel_id'))->toContain('paid_channel');
    });

    test('matches contains operators case-insensitively and negated operators on null fields', function () {
        $this->game->tags()->create(['name' => 'Romance', 'slug' => 'romance-case']);
        $this->game->load('tags');
        $this->server->config->update([
            'routing_rules' => [[
                'enabled' => true,
                'priority' => 1,
                'conditions' => [
                    ['field' => 'tags', 'operator' => 'contains', 'value' => 'ROMANCE'],
                    ['field' => 'unknown_field', 'operator' => 'not_equals', 'value' => 'anything'],
                ],
                'action' => ['type' => 'route', 'channel_id' => 'romance_channel'],
            ]],
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');
        expect(array_column($result->getTargetChannels(), 'channel_id'))->toContain('romance_channel');
    });

    test('returns empty when no config exists', function () {
        $newServer = DiscordServer::factory()->create();
        $result = $this->service->evaluateRoutes($newServer, $this->game, 'update');

        expect($result->shouldSkip)->toBeFalse()
            ->and($result->hasChannels())->toBeFalse();
    });

    test('multi-channel routing accumulates from rules and overrides', function () {
        DiscordServerGameOverride::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'is_ignored' => false,
            'channel_id' => 'override_channel',
        ]);

        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Route paid',
                    'enabled' => true,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'is_paid', 'operator' => 'equals', 'value' => true],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => 'paid_channel'],
                ],
            ],
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        $channelIds = array_column($result->getTargetChannels(), 'channel_id');
        expect($channelIds)->toContain('paid_channel')
            ->and($channelIds)->toContain('override_channel');
    });

    test('deduplicates channels by channel_id', function () {
        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Same channel',
                    'enabled' => true,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'is_paid', 'operator' => 'equals', 'value' => true],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => '111111111'],
                ],
            ],
        ]);

        $result = $this->service->evaluateRoutes($this->server, $this->game, 'update');

        $channels = $result->getTargetChannels();
        $channelIds = array_column($channels, 'channel_id');
        $uniqueIds = array_unique($channelIds);
        expect(count($channelIds))->toBe(count($uniqueIds));
    });

    test('does not route nsfw games to a non-nsfw default channel', function () {
        $nsfwGame = Game::factory()->create(['is_nsfw' => true]);

        $result = $this->service->evaluateRoutes($this->server, $nsfwGame, 'update');

        expect($result->shouldSkip)->toBeFalse()
            ->and($result->hasChannels())->toBeFalse();
    });

    test('routes nsfw games to an nsfw rule target channel', function () {
        $this->server->config->update([
            'routing_rules' => [
                [
                    'id' => 'r1',
                    'name' => 'Route NSFW',
                    'enabled' => true,
                    'priority' => 1,
                    'conditions' => [
                        ['field' => 'is_nsfw', 'operator' => 'equals', 'value' => true],
                    ],
                    'action' => ['type' => 'route', 'channel_id' => '222222222'],
                ],
            ],
        ]);

        $nsfwGame = Game::factory()->create(['is_nsfw' => true]);
        $result = $this->service->evaluateRoutes($this->server, $nsfwGame, 'update');

        expect($result->getTargetChannels())->toHaveCount(1)
            ->and($result->getTargetChannels()[0]['channel_id'])->toBe('222222222');
    });

    test('allows NSFW routing when channel metadata is unavailable', function () {
        $this->server->update(['available_channels' => null]);
        $nsfwGame = Game::factory()->create(['is_nsfw' => true]);

        $result = $this->service->evaluateRoutes($this->server->fresh(), $nsfwGame, 'update');

        expect($result->getTargetChannels())->toHaveCount(1)
            ->and($result->getTargetChannels()[0]['channel_id'])->toBe('111111111');
    });

    test('does not route nsfw games to a non-nsfw override channel', function () {
        DiscordServerGameOverride::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $this->game->id,
            'is_ignored' => false,
            'channel_id' => 'paid_channel',
        ]);

        $nsfwGame = Game::factory()->create(['is_nsfw' => true]);
        DiscordServerGameOverride::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $nsfwGame->id,
            'is_ignored' => false,
            'channel_id' => 'paid_channel',
        ]);

        $result = $this->service->evaluateRoutes($this->server, $nsfwGame, 'update');

        expect($result->hasChannels())->toBeFalse();
    });

    test('allows nsfw override channels for nsfw games', function () {
        $nsfwGame = Game::factory()->create(['is_nsfw' => true]);
        DiscordServerGameOverride::create([
            'discord_server_id' => $this->server->id,
            'game_id' => $nsfwGame->id,
            'is_ignored' => false,
            'channel_id' => 'override_channel',
        ]);

        $result = $this->service->evaluateRoutes($this->server, $nsfwGame, 'update');

        expect($result->getTargetChannels())->toHaveCount(1)
            ->and($result->getTargetChannels()[0]['channel_id'])->toBe('override_channel');
    });
});
