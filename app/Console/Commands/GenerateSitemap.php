<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\News;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap.xml file';

    public function handle(): void
    {
        // Create new sitemap
        $sitemap = Sitemap::create();

        // Add homepage
        $sitemap->add(
            Url::create('/')
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setLastModificationDate(now())
        );

        // Add game list pages
        $gamesPerPage = 9; // Default games per page from GameList component
        $totalGames = Game::where('is_visible', true)->count();
        $totalPages = ceil($totalGames / $gamesPerPage);

        // Add first page of games (homepage)
        $sitemap->add(
            Url::create(route('games.index'))
                ->setPriority(0.9)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setLastModificationDate(now())
        );

        // Add subsequent pages
        for ($page = 2; $page <= $totalPages; $page++) {
            $sitemap->add(
                Url::create(route('games.index', ['page' => $page]))
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setLastModificationDate(now())
            );
        }

        // Add individual game pages
        $games = Game::where('is_visible', true)
            ->whereNotNull('slug')
            ->select(['slug', 'updated_at'])
            ->get();

        foreach ($games as $game) {
            $sitemap->add(
                Url::create(route('games.show', $game))
                    ->setPriority(0.9)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_WEEKLY)
                    ->setLastModificationDate($game->updated_at)
            );
        }

        // Add news index page
        $sitemap->add(
            Url::create(route('news.index'))
                ->setPriority(0.7)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setLastModificationDate(now())
        );

        // Add individual news pages
        $newsItems = News::published()
            ->select(['slug', 'updated_at'])
            ->get();

        foreach ($newsItems as $newsItem) {
            $sitemap->add(
                Url::create(route('news.show', $newsItem))
                    ->setPriority(0.6)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_MONTHLY)
                    ->setLastModificationDate($newsItem->updated_at)
            );
        }

        // Write sitemap to public directory
        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully!');
    }
}
