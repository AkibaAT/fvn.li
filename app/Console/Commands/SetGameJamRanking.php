<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameJam;
use Illuminate\Console\Command;

class SetGameJamRanking extends Command
{
    protected $signature = 'game-jams:set-ranking
        {--game-url= : The URL of the game}
        {--jam-url= : The URL of the game jam}
        {--ranking= : The ranking to set (e.g., "4th")}';

    protected $description = 'Set the ranking for a game in a game jam';

    public function handle(): int
    {
        $gameUrl = $this->option('game-url');
        $jamUrl = $this->option('jam-url');
        $ranking = $this->option('ranking');

        if (! $gameUrl || ! $jamUrl || ! $ranking) {
            $this->error('You must provide --game-url, --jam-url, and --ranking options');

            return 1;
        }

        // Find the game
        $game = Game::where('url', $gameUrl)->first();
        if (! $game) {
            $this->error("Game not found with URL: {$gameUrl}");

            return 1;
        }

        // Find the game jam
        $gameJam = GameJam::where('url', 'like', "%{$jamUrl}%")->first();
        if (! $gameJam) {
            $this->error("Game jam not found with URL: {$jamUrl}");

            return 1;
        }

        // Check if the game is already associated with the jam
        if (! $game->gameJams()->where('game_jam_id', $gameJam->id)->exists()) {
            // Associate the game with the jam
            $game->gameJams()->attach($gameJam->id, ['ranking' => $ranking]);
            $this->info("Associated game '{$game->name}' with jam '{$gameJam->name}' and set ranking to '{$ranking}'");
        } else {
            // Update the existing association
            $game->gameJams()->updateExistingPivot($gameJam->id, ['ranking' => $ranking]);
            $this->info("Updated ranking for game '{$game->name}' in jam '{$gameJam->name}' to '{$ranking}'");
        }

        return 0;
    }
}
