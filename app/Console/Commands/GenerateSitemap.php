<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate';

    protected $description = 'Generate the sitemap.xml file';

    public function handle(): void
    {
        $sitemap = Sitemap::create();

        $sitemap->add(
            Url::create('/')
                ->setPriority(1.0)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setLastModificationDate(now())
        );

        $gamesPerPage = 9; // Default games per page from GameList component
        $totalGames = Game::where('is_visible', true)->count();
        $totalPages = ceil($totalGames / $gamesPerPage);

        $sitemap->add(
            Url::create(route('games.index'))
                ->setPriority(0.9)
                ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                ->setLastModificationDate(now())
        );

        for ($page = 2; $page <= $totalPages; $page++) {
            $sitemap->add(
                Url::create(route('games.index', ['page' => $page]))
                    ->setPriority(0.8)
                    ->setChangeFrequency(Url::CHANGE_FREQUENCY_DAILY)
                    ->setLastModificationDate(now())
            );
        }

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

        // Write sitemap to public directory
        $sitemap->writeToFile(public_path('sitemap.xml'));

        $this->info('Sitemap generated successfully.');
    }
}
