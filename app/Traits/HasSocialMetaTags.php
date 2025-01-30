<?php

declare(strict_types=1);

namespace App\Traits;

use App\Livewire\RaterDetail;
use App\Models\Language;
use Illuminate\Support\Str;

trait HasSocialMetaTags
{
    protected function getMetaTitle(): string
    {
        $title = '';

        if ($this instanceof RaterDetail) {
            $title = "{$this->totalRatingsCount} ratings";

            if ($this->visibleGamesRatingsCount < $this->totalRatingsCount) {
                $title .= " ({$this->visibleGamesRatingsCount} in listed games)";
            }

            return $title . ' - ' . config('app.name');
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

            if (! empty($this->selectedStatuses)) {
                $statuses = array_map(fn ($s) => strtolower($this->decodeFilterValue($s)), $this->selectedStatuses);
                $filters[] = implode('/', $statuses);
            }

            if (! empty($this->selectedEngines)) {
                $engines = array_map(fn ($e) => "made with {$this->decodeFilterValue($e)}", $this->selectedEngines);
                $filters[] = implode(' and ', $engines);
            }

            if (! empty($this->selectedPlatforms)) {
                $platforms = array_map(fn ($p) => 'for ' . ucfirst($this->decodeFilterValue($p)), $this->selectedPlatforms);
                $filters[] = implode(' and ', $platforms);
            }

            if (! empty($this->selectedLanguages)) {
                $languages = Language::whereIn('id', array_map([$this, 'decodeFilterValue'], $this->selectedLanguages))
                    ->pluck('ref_name')
                    ->map(fn ($lang) => "in {$lang}")
                    ->implode(' and ');
                $filters[] = $languages;
            }

            if ($this->nsfw) {
                $filters[] = 'NSFW';
            }

            if ($this->search) {
                $filters[] = "matching '{$this->search}'";
            }

            if (! empty($filters)) {
                $title .= ' that are ' . implode(', ', $filters);
            }
        }

        return $title . ' - ' . config('app.name');
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
            if (! empty($this->selectedStatuses)) {
                $statuses = array_map(fn ($s) => strtolower($this->decodeFilterValue($s)), $this->selectedStatuses);
                $filters[] = implode(' and ', $statuses);
            }

            // Build engine filter
            if (! empty($this->selectedEngines)) {
                $engines = array_map(fn ($e) => $this->decodeFilterValue($e), $this->selectedEngines);
                $filters[] = 'created with ' . implode(' and ', $engines);
            }

            // Add NSFW/SFW status
            if ($this->nsfw) {
                $description .= ' NSFW';
            } elseif ($this->sfw) {
                $description .= ' SFW';
            }

            // Add platform information
            if (! empty($this->selectedPlatforms)) {
                $platforms = array_map(fn ($p) => ucfirst($this->decodeFilterValue($p)), $this->selectedPlatforms);
                $description .= ' ' . implode('/', $platforms);
            }

            $description .= ' FVNs';

            // Add filters
            if (! empty($filters)) {
                $description .= ' that are ' . implode(' and ', $filters);
            }

            // Add language information
            if (! empty($this->selectedLanguages)) {
                $languages = Language::whereIn('id', array_map([$this, 'decodeFilterValue'], $this->selectedLanguages))
                    ->pluck('ref_name')
                    ->implode('/');
                $description .= " in {$languages}";
            }

            // Add search term
            if ($this->search) {
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
            if (property_exists($this, 'sortField') &&
                ($this->sortField !== 'latest_version_published_at' || $this->sortDirection !== 'desc')) {
                $sortLabels = $this::AVAILABLE_SORT_FIELDS;

                $description .= ", sorted by {$sortLabels[$this->sortField]} " .
                    ($this->sortDirection === 'asc' ? 'ascending' : 'descending');
            }

            $description .= '.';

            return $description;
        }

        return config('app.description', 'Default description');
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
