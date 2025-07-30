<?php

declare(strict_types=1);

namespace App\Traits;

use App\Livewire\RaterDetail;
use App\Models\Language;
use Illuminate\Support\Str;

trait HasSocialMetaTags
{
    use HasDefaultSort;

    protected function getMetaTitle(): string
    {
        $title = '';

        if ($this instanceof RaterDetail) {
            $title = "{$this->totalRatingsCount} ratings";

            if ($this->visibleGamesRatingsCount < $this->totalRatingsCount) {
                $title .= " ({$this->visibleGamesRatingsCount} in listed games)";
            }

            return $title;
        }

        if (method_exists($this, 'getHeading')) {
            $totalRecords = $this->getAllTableRecordsCount();
            $title = "{$totalRecords} " . Str::plural(rtrim(strtolower($this->getHeading()), 's'), $totalRecords);
        }

        // For Livewire game list
        if (property_exists($this, 'games')) {
            $totalRecords = $this->games->total();
            $title = "{$totalRecords} FVNs";

            // Add filter information
            $filters = [];

            if (property_exists($this, 'selectedStatuses') && ! empty($this->selectedStatuses)) {
                $statuses = array_map('strtolower', $this->selectedStatuses);
                $filters[] = implode('/', $statuses);
            }

            if (property_exists($this, 'selectedEngines') && ! empty($this->selectedEngines)) {
                $engines = array_map(fn ($e) => "made with {$e}", $this->selectedEngines);
                $filters[] = implode(' and ', $engines);
            }

            if (property_exists($this, 'selectedPlatforms') && ! empty($this->selectedPlatforms)) {
                $platforms = array_map(fn ($p) => 'for ' . ucfirst($p),
                    $this->selectedPlatforms);
                $filters[] = implode(' and ', $platforms);
            }

            if (property_exists($this, 'selectedLanguages') && ! empty($this->selectedLanguages)) {
                $languages = Language::whereIn('id', $this->selectedLanguages)
                    ->pluck('ref_name')
                    ->map(fn ($lang) => "in {$lang}")
                    ->implode(' and ');
                $filters[] = $languages;
            }

            if (property_exists($this, 'nsfw') && $this->nsfw) {
                $filters[] = 'NSFW';
            }

            if (property_exists($this, 'search') && $this->search) {
                $filters[] = "matching '{$this->search}'";
            }

            if (! empty($filters)) {
                $title .= ' that are ' . implode(', ', $filters);
            }
        }

        return $title;
    }

    protected function getMetaDescription(): string
    {
        if ($this instanceof RaterDetail) {
            $description = "Viewing {$this->rater->id}'s game ratings";

            $description .= ". Total ratings: {$this->totalRatingsCount}, ";
            $description .= "Listed game ratings: {$this->visibleGamesRatingsCount}. ";

            return $description;
        }

        // For game list
        if (property_exists($this, 'games')) {
            $description = 'Browse';

            $filters = [];

            // Build status filter
            if (property_exists($this, 'selectedStatuses') && ! empty($this->selectedStatuses)) {
                $statuses = array_map('strtolower', $this->selectedStatuses);
                $filters[] = implode(' and ', $statuses);
            }

            // Build engine filter
            if (property_exists($this, 'selectedEngines') && ! empty($this->selectedEngines)) {
                $filters[] = 'created with ' . implode(' and ', $this->selectedEngines);
            }

            // Add NSFW/SFW status
            if (property_exists($this, 'nsfw') && $this->nsfw) {
                $description .= ' NSFW';
            } elseif (property_exists($this, 'sfw') && $this->sfw) {
                $description .= ' SFW';
            }

            // Add platform information
            if (property_exists($this, 'selectedPlatforms') && ! empty($this->selectedPlatforms)) {
                $platforms = array_map('ucfirst', $this->selectedPlatforms);
                $description .= ' ' . implode('/', $platforms);
            }

            $description .= ' FVNs';

            // Add filters
            if (! empty($filters)) {
                $description .= ' that are ' . implode(' and ', $filters);
            }

            // Add language information
            if (property_exists($this, 'selectedLanguages') && ! empty($this->selectedLanguages)) {
                $languages = Language::whereIn('id', $this->selectedLanguages)
                    ->pluck('ref_name')
                    ->implode('/');
                $description .= " in {$languages}";
            }

            // Add search term
            if (property_exists($this, 'search') && $this->search) {
                $description .= " matching '{$this->search}'";
            }

            // Add game count
            $description .= ". Featuring {$this->games->total()} titles";

            // Add featured games
            $featuredGames = $this->games->take(4)->pluck('name')->implode(', ');
            if ($featuredGames) {
                $description .= ", including: {$featuredGames}";
            }

            // Add sort information if not default
            if (property_exists($this, 'sortField') && property_exists($this, 'sortDirection') &&
                ! $this->isDefaultSort($this->sortField, $this->sortDirection)) {
                $sortLabel = method_exists($this, 'getSortLabel') ?
                    $this->getSortLabel($this->sortField) :
                    ucfirst(str_replace('_', ' ', $this->sortField));

                $description .= ", sorted by {$sortLabel} " .
                    ($this->sortDirection === 'asc' ? 'ascending' : 'descending');
            }

            $description .= '.';

            return $description;
        }

        return (string) config('app.description', 'Default description');
    }

    protected function getMetaImage(): string
    {
        if (property_exists($this, 'games') && $this->games->count() > 0) {
            foreach ($this->games as $game) {
                if ($game->thumb_url) {
                    return $game->thumb_url;
                }
            }
        }

        return '';
    }
}
