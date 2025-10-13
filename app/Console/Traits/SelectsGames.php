<?php

declare(strict_types=1);

namespace App\Console\Traits;

use App\Models\Game;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Trait for commands that need to select games by ID or name
 */
trait SelectsGames
{
    /**
     * Get games based on selection criteria
     *
     * @param  array  $additionalWhere  Additional where clauses to apply
     * @param  array  $with  Relationships to eager load
     * @return Collection The selected games
     */
    protected function getSelectedGames(array $additionalWhere = [], array $with = []): Collection
    {
        $query = Game::query();

        // Apply any additional where clauses
        foreach ($additionalWhere as $column => $value) {
            $query->where($column, $value);
        }

        // Eager load relationships if specified
        if (! empty($with)) {
            $query->with($with);
        }

        // Apply game selection filters
        $this->applyGameSelectionFilters($query);

        return $query->get();
    }

    /**
     * Apply game selection filters to a query based on command options
     *
     * @param  Builder  $query  The base query to modify
     * @return Builder The modified query
     */
    protected function applyGameSelectionFilters(Builder $query): Builder
    {
        // If game ID is provided, filter by ID
        if ($gameId = $this->option('game-id')) {
            $query->where('id', (int) $gameId);
        } // If game name is provided, filter by name (case-insensitive partial match)
        elseif ($gameName = $this->option('game-name')) {
            $query->where('name', 'ilike', "%{$gameName}%");
        }

        return $query;
    }

    /**
     * Validate that at least one game selection option is provided
     *
     * @return bool Whether the validation passes
     */
    protected function validateGameSelectionOptions(): bool
    {
        if (! $this->option('game-id') && ! $this->option('game-name') && ! $this->option('all')) {
            $this->error('You must provide either --game-id, --game-name, or --all option');

            return false;
        }

        return true;
    }

    /**
     * Display the selected games
     *
     * @param  Collection  $games  The selected games
     */
    protected function displaySelectedGames(Collection $games): void
    {
        $count = $games->count();

        if ($count === 0) {
            $this->warn('No games found matching the selection criteria');

            return;
        }

        $this->info("Found {$count} game(s):");
        foreach ($games as $game) {
            $this->line("- {$game->name} (ID: {$game->id}, Status: {$game->status})");
        }
    }
}
