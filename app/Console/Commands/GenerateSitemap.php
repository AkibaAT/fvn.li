<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\GameVersion;
use App\Models\Tag;
use Carbon\CarbonInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap.xml file';

    public function handle(): void
    {
        $sitemap = Sitemap::create();

        $games = Game::where('is_visible', true)
            ->whereNotNull('slug')
            ->select(['id', 'slug', 'name', 'custom_name', 'view_mode', 'has_custom_page', 'optimized_thumbnails', 'first_visible_at', 'initially_published_at', 'custom_page_updated_at', 'created_at'])
            ->addSelect(['latest_version_published_at' => GameVersion::query()
                ->select('published_at')
                ->whereColumn('game_versions.game_id', 'games.id')
                ->where('is_latest', true)
                ->limit(1),
            ])
            ->get();

        $catalogueModified = $games->map(fn (Game $game) => $this->contentModifiedAt($game))->filter()->max();

        foreach (['home' => 1.0, 'games.index' => 0.9, 'tags.index' => 0.7] as $route => $priority) {
            $sitemap->add($this->url(route($route), $catalogueModified, $priority, Url::CHANGE_FREQUENCY_DAILY));
        }

        $sitemap->add($this->url(route('lists.public'), null, 0.5, Url::CHANGE_FREQUENCY_DAILY));
        $sitemap->add($this->url(route('ratings.index'), null, 0.5, Url::CHANGE_FREQUENCY_DAILY));

        foreach ($games as $game) {
            $url = $this->url(route('games.show', $game), $this->contentModifiedAt($game), 0.9, Url::CHANGE_FREQUENCY_WEEKLY);

            if ($thumbnail = $game->getThumbnailUrl('default')) {
                $url->addImage($thumbnail, title: $game->effective_name);
            }

            $sitemap->add($url);
        }

        $tagModified = DB::table('game_tag')
            ->join('games', 'games.id', '=', 'game_tag.game_id')
            ->leftJoin('game_versions', fn ($join) => $join->on('game_versions.game_id', '=', 'games.id')->where('game_versions.is_latest', true))
            ->where('games.is_visible', true)
            ->whereIn('game_tag.tag_id', Tag::popularIds())
            ->groupBy('game_tag.tag_id')
            ->select('game_tag.tag_id', DB::raw('MAX(COALESCE(game_versions.published_at, games.first_visible_at, games.created_at)) as modified_at'))
            ->pluck('modified_at', 'tag_id');

        Tag::whereIn('id', $tagModified->keys())
            ->orderBy('name')
            ->get(['id', 'slug'])
            ->each(fn (Tag $tag) => $sitemap->add(
                $this->url(route('tags.show', $tag), Carbon::parse($tagModified[$tag->id]), 0.6, Url::CHANGE_FREQUENCY_WEEKLY)
            ));

        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully.');
    }

    private function contentModifiedAt(Game $game): ?CarbonInterface
    {
        return collect([
            $game->latest_version_published_at,
            $game->custom_page_updated_at,
            $game->first_visible_at,
            $game->initially_published_at,
        ])->filter()->max() ?? $game->created_at;
    }

    private function url(string $location, ?CarbonInterface $modifiedAt, float $priority, string $frequency): Url
    {
        $url = Url::create($location)
            ->setPriority($priority)
            ->setChangeFrequency($frequency);

        return $modifiedAt ? $url->setLastModificationDate($modifiedAt) : $url;
    }
}
