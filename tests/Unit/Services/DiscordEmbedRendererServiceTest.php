<?php

declare(strict_types=1);

use App\Models\DiscordServer;
use App\Models\Game;
use App\Models\GameVersion;
use App\Services\Discord\DiscordEmbedRendererService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Game::unsetEventDispatcher();

    $this->service = new DiscordEmbedRendererService;
    $this->game = Game::factory()->create([
        'name' => 'Test VN',
        'slug' => 'test-vn',
        'status' => 'Released',
        'is_nsfw' => false,
        'is_paid' => true,
        'authors' => 'TestDev',
        'developer' => 'TestDev',
        'description' => 'A test visual novel description.',
    ]);

    $this->version = GameVersion::factory()->create([
        'game_id' => $this->game->id,
        'version' => '1.0.0',
    ]);

    $this->server = DiscordServer::factory()->create([
        'discord_server_name' => 'Test Server',
    ]);
});

describe('DiscordEmbedRendererService', function () {
    test('substitutes variables in embed template', function () {
        $template = [
            'title' => '{game.name}',
            'description' => 'Version {version.name} released!',
            'url' => '{game.url}',
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', $this->version, $this->server);

        expect($result['title'])->toBe('Test VN')
            ->and($result['description'])->toBe('Version 1.0.0 released!')
            ->and($result['url'])->toBe(route('games.show', $this->game->slug));
    });

    test('handles missing game version gracefully', function () {
        $template = [
            'title' => '{game.name}',
            'description' => '{version.name}',
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect($result['title'])->toBe('Test VN')
            ->and($result)->not->toHaveKey('description');
    });

    test('substitutes all game variables', function () {
        $template = [
            'title' => '{game.name}',
            'description' => '{game.description}',
            'footer' => ['text' => '{game.status} by {game.developer}'],
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect($result['title'])->toBe('Test VN')
            ->and($result['description'])->toBe('A test visual novel description.')
            ->and($result['footer']['text'])->toBe('Released by TestDev');
    });

    test('enforces title length limit', function () {
        $template = [
            'title' => str_repeat('A', 300),
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect(mb_strlen($result['title']))->toBeLessThanOrEqual(256)
            ->and(mb_strlen($result['title']))->toBe(256);
    });

    test('enforces description length limit', function () {
        $template = [
            'description' => str_repeat('A', 5000),
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect(mb_strlen($result['description']))->toBeLessThanOrEqual(4096);
    });

    test('enforces author name length limit', function () {
        $template = [
            'author' => ['name' => str_repeat('A', 300)],
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect(mb_strlen($result['author']['name']))->toBeLessThanOrEqual(256)
            ->and(mb_strlen($result['author']['name']))->toBe(256);
    });

    test('enforces field limits', function () {
        $fields = [];
        for ($i = 0; $i < 30; $i++) {
            $fields[] = ['name' => "Field {$i}", 'value' => str_repeat('B', 1100), 'inline' => true];
        }

        $template = [
            'title' => 'Test',
            'fields' => $fields,
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect(count($result['fields']))->toBe(25);
        foreach ($result['fields'] as $field) {
            expect(mb_strlen($field['name']))->toBeLessThanOrEqual(256);
            expect(mb_strlen($field['value']))->toBeLessThanOrEqual(1024);
        }
    });

    test('removes empty string values', function () {
        $template = [
            'title' => '{game.name}',
            'description' => '',
            'footer' => ['text' => '', 'icon_url' => ''],
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect($result)->toHaveKey('title')
            ->and($result)->not->toHaveKey('description')
            ->and($result)->not->toHaveKey('footer');
    });

    test('preserves numeric values like color', function () {
        $template = [
            'title' => '{game.name}',
            'color' => 5763719,
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect($result['color'])->toBe(5763719);
    });

    test('default new game embed renders without errors', function () {
        $template = $this->service->getDefaultNewGameEmbed();

        $result = $this->service->renderEmbed($template, $this->game, 'new_game', $this->version, $this->server);

        expect($result)->toHaveKey('title')
            ->and($result)->toHaveKey('fields');
    });

    test('default update embed renders without errors', function () {
        $template = $this->service->getDefaultUpdateEmbed();
        $this->version->update([
            'devlog' => 'https://example.com/devlog/test-vn-1-0-0',
        ]);
        $this->version->refresh();

        $result = $this->service->renderEmbed($template, $this->game, 'update', $this->version, $this->server);

        expect($result)->toHaveKey('title')
            ->and($result)->toHaveKey('fields')
            ->and(collect($result['fields'])->pluck('name')->all())->toContain('Devlog')
            ->and(collect($result['fields'])->firstWhere('name', 'Devlog')['value'])->toBe('[Read devlog](https://example.com/devlog/test-vn-1-0-0)');
    });

    test('renderText substitutes variables in plain text', function () {
        $template = '{game.name} version {version.name} has been released!';

        $result = $this->service->renderText($template, $this->game, 'update', $this->version, $this->server);

        expect($result)->toBe('Test VN version 1.0.0 has been released!');
    });

    test('strips html from embed text fields', function () {
        $this->game->update([
            'name' => 'Test <b>VN</b>',
            'description' => '<p>Hello <strong>world</strong> &amp; friends</p>',
            'developer' => '<a href="https://example.com">TestDev</a>',
        ]);
        $this->game->refresh();

        $template = [
            'title' => '{game.name}',
            'description' => '{game.description}',
            'footer' => ['text' => '<em>{game.developer}</em>'],
            'fields' => [
                ['name' => '<b>Status</b>', 'value' => '<div>{game.developer}</div>', 'inline' => true],
            ],
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect($result['title'])->toBe('Test VN')
            ->and($result['description'])->toBe('Hello world & friends')
            ->and($result['footer']['text'])->toBe('TestDev')
            ->and($result['fields'][0]['name'])->toBe('Status')
            ->and($result['fields'][0]['value'])->toBe('TestDev');
    });

    test('preserves discord timestamp syntax while stripping html', function () {
        $template = [
            'description' => '<strong>Released</strong> {version.published_at_discord}',
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', $this->version, $this->server);

        expect($result['description'])->toMatch('/^Released <t:\d+:f>$/');
    });

    test('enforces total embed text length across all fields', function () {
        $template = [
            'title' => str_repeat('T', 256),
            'author' => ['name' => str_repeat('A', 256)],
            'footer' => ['text' => str_repeat('F', 2048)],
            'fields' => [
                ['name' => str_repeat('N', 256), 'value' => str_repeat('V', 1024), 'inline' => true],
                ['name' => str_repeat('N', 256), 'value' => str_repeat('V', 1024), 'inline' => true],
                ['name' => str_repeat('N', 256), 'value' => str_repeat('V', 1024), 'inline' => true],
            ],
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        $totalLength = mb_strlen($result['title'] ?? '')
            + mb_strlen($result['description'] ?? '')
            + mb_strlen($result['author']['name'] ?? '')
            + mb_strlen($result['footer']['text'] ?? '');

        foreach ($result['fields'] ?? [] as $field) {
            $totalLength += mb_strlen($field['name'] ?? '');
            $totalLength += mb_strlen($field['value'] ?? '');
        }

        expect($totalLength)->toBeLessThanOrEqual(6000);
    });

    test('falls back to authors when developer is empty', function () {
        $this->game->update([
            'developer' => '',
            'authors' => '<a href="https://example.com">Studio Example</a>',
        ]);
        $this->game->refresh();

        $template = [
            'fields' => [
                ['name' => 'Developer', 'value' => '{game.developer}', 'inline' => true],
            ],
        ];

        $result = $this->service->renderEmbed($template, $this->game, 'update', null, $this->server);

        expect($result['fields'][0]['value'])->toBe('Studio Example');
    });

    test('omits default update devlog field when no devlog url exists', function () {
        $this->version->update(['devlog' => null]);
        $this->version->refresh();

        $result = $this->service->renderEmbed(
            $this->service->getDefaultUpdateEmbed(),
            $this->game,
            'update',
            $this->version,
            $this->server,
        );

        expect(collect($result['fields'])->pluck('name')->all())->not->toContain('Devlog');
    });
});
