<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Game;
use App\Models\GameDialogueText;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class GameModerationPurger
{
    public function __construct(private readonly DenKitStashPersistenceService $stash) {}

    public function purge(Game $game): void
    {
        $this->stash->deleteTarget($game);

        $mediaPaths = $this->mediaPaths($game);

        DB::transaction(function () use ($game) {
            $game->gameVersions()->delete();
            $game->characters()->delete();
            $game->languageMappings()->delete();
            $game->tags()->detach();
            $game->gameJams()->detach();

            $game->forceFill(Game::CONTENT_ATTRIBUTES)->save();
        });

        $public = Storage::disk('public');
        $public->delete([
            ...$mediaPaths,
            ...$this->filesWithPrefix('thumbnails', "{$game->id}_"),
            ...$this->filesWithPrefix('screenshots', "{$game->id}_"),
        ]);
        $public->deleteDirectory("games/{$game->id}");
        $public->deleteDirectory("editor/{$game->id}");
        $public->deleteDirectory("game-assets/{$game->slug}");
        Storage::deleteDirectory("games/{$game->id}");

        GameDialogueText::deleteSearchDocumentsForGame($game->id);
        GameStatsService::clearCache($game->id);
    }

    /**
     * @return list<string>
     */
    private function mediaPaths(Game $game): array
    {
        $media = [$game->optimized_thumbnails, $game->screenshots, $game->custom_screenshots, $game->custom_assets];

        return collect(Arr::dot(array_filter($media)))
            ->filter(fn ($value, string $key) => str_ends_with($key, '.path') && is_string($value))
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function filesWithPrefix(string $directory, string $prefix): array
    {
        return array_values(array_filter(
            Storage::disk('public')->files($directory),
            fn (string $file) => str_starts_with(basename($file), $prefix),
        ));
    }
}
