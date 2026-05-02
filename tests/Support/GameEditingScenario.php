<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\Game;
use App\Models\SocialAccount;
use App\Models\User;

final class GameEditingScenario
{
    public function __construct(
        public readonly User $developer,
        public readonly Game $game,
    ) {}

    public static function ownedItchGame(array $gameAttributes = [], array $userAttributes = []): self
    {
        $developer = User::factory()->create($userAttributes);

        $game = Game::factory()->create(array_merge([
            'itch_id' => 123456,
            'name' => 'Fixture Game',
            'platform' => 'itch_io',
            'url' => ['itch_io' => 'https://fixture-dev.itch.io/fixture-game'],
            'is_visible' => true,
            'description' => '<p>Itch.io description.</p>',
            'custom_description' => '<p>Custom description.</p>',
            'screenshots' => [
                ['url' => 'https://img.itch.zone/fixture-1.png'],
            ],
            'custom_screenshots' => [
                ['url' => 'https://fvn-li.ddev.site/storage/games/1/screenshots/custom-1.webp'],
            ],
            'view_mode' => 'custom',
        ], $gameAttributes));

        SocialAccount::factory()->create([
            'user_id' => $developer->id,
            'provider_name' => 'itchio',
            'provider_data' => [
                'username' => 'fixture-dev',
                'url' => 'https://fixture-dev.itch.io',
            ],
            'itchio_game_ids' => [$game->itch_id],
        ]);

        return new self($developer, $game);
    }
}
