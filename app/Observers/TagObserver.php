<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Tag;
use App\Services\GameFilterService;
use App\Services\GamePageCacheService;
use App\Services\ObserverSearchIndexService;
use Illuminate\Support\Facades\Cache;

class TagObserver
{
    public function updated(Tag $tag): void
    {
        // If the tag name changed, update all related games
        if ($tag->isDirty('name')) {
            GamePageCacheService::invalidateMany($tag->games()->pluck('games.id'));
            $this->updateRelatedGames($tag);
            GameFilterService::clearCache();
        }
    }

    public function deleting(Tag $tag): void
    {
        GamePageCacheService::invalidateMany($tag->games()->pluck('games.id'));
    }

    public function deleted(Tag $tag): void
    {
        $this->updateRelatedGames($tag);
        GameFilterService::clearCache();
    }

    private function updateRelatedGames(Tag $tag): void
    {
        if (app(ObserverSearchIndexService::class)->reindexGames(
            $tag->games(),
            'tag change',
            [
                'tag_id' => $tag->id,
                'tag_name' => $tag->name,
            ]
        )) {
            Cache::add('games.recommendations.version', 1);
            Cache::increment('games.recommendations.version');
        }
    }
}
