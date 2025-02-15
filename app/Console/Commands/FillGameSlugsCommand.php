<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class FillGameSlugsCommand extends Command
{
    protected $signature = 'games:fill-slugs {--force : Force regenerate all slugs}';
    protected $description = 'Fill missing slugs for games';

    public function handle(): int
    {
        $query = Game::query();

        if (! $this->option('force')) {
            $query->whereNull('slug');
        }

        $count = $query->count();

        if ($count === 0) {
            $this->info('No games need slug generation.');

            return self::SUCCESS;
        }

        $this->info("Processing {$count} games...");
        $bar = $this->output->createProgressBar($count);

        $query->chunk(100, function ($games) use ($bar) {
            foreach ($games as $game) {
                $this->generateSlug($game);
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine();
        $this->info('Slug generation completed.');

        return self::SUCCESS;
    }

    protected function generateSlug(Game $game): void
    {
        // Get base slug from game URL
        $baseSlug = basename($game->url);

        // If URL doesn't provide a usable slug, generate from name
        if (empty($baseSlug) || $baseSlug === '/') {
            $baseSlug = Str::slug($game->name);
        }

        // Find a unique slug
        $slug = $baseSlug;
        $counter = 1;

        while (Game::where('slug', $slug)
            ->where('id', '!=', $game->id)
            ->exists()) {
            $slug = $baseSlug . '-' . $counter++;
        }

        $game->slug = $slug;
        $game->save();
    }
}
