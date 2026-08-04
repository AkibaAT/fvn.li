<?php

declare(strict_types=1);

namespace App\Console\Traits;

use App\Models\GameJam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Trait for commands that need to select game jams by ID, name, or URL
 */
trait SelectsGameJams
{
    /**
     * Get game jams based on selection criteria
     *
     * @param  array  $additionalWhere  Additional where clauses to apply
     * @param  array  $with  Relationships to eager load
     * @return Collection The selected game jams
     */
    protected function getSelectedGameJams(array $additionalWhere = [], array $with = []): Collection
    {
        $query = GameJam::query();

        foreach ($additionalWhere as $column => $value) {
            $query->where($column, $value);
        }

        // Eager load relationships if specified
        if (! empty($with)) {
            $query->with($with);
        }

        $this->applyGameJamSelectionFilters($query);

        return $query->get();
    }

    /**
     * Apply game jam selection filters to a query based on command options
     *
     * @param  Builder  $query  The base query to modify
     * @return Builder The modified query
     */
    protected function applyGameJamSelectionFilters(Builder $query): Builder
    {
        // If game jam ID is provided, filter by ID
        if ($jamId = $this->option('id')) {
            $query->where('id', $jamId);
        } // If game jam name is provided, filter by name (case-insensitive partial match)
        elseif ($jamName = $this->option('name')) {
            $query->where('name', 'ilike', "%{$jamName}%");
        } // If URL is provided, filter by URL
        elseif ($url = $this->option('url')) {
            $query->where('url', $url);
        }

        return $query;
    }

    /**
     * Validate that at least one game jam selection option is provided
     *
     * @return bool Whether the validation passes
     */
    protected function validateGameJamSelectionOptions(): bool
    {
        if (! $this->option('id') && ! $this->option('name') && ! $this->option('url') && ! $this->option('all')) {
            $this->error('You must provide either --id, --name, --url, or --all option');

            return false;
        }

        return true;
    }

    /**
     * Display the selected game jams
     *
     * @param  Collection  $gameJams  The selected game jams
     */
    protected function displaySelectedGameJams(Collection $gameJams): void
    {
        $count = $gameJams->count();

        if ($count === 0) {
            $this->warn('No game jams found matching the selection criteria');

            return;
        }

        $this->info("Found {$count} game jam(s):");
        foreach ($gameJams as $jam) {
            $this->line("- {$jam->name} (ID: {$jam->id})");
        }
    }
}
