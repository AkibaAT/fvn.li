<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Language;
use Illuminate\Support\Str;

trait HasSocialMetaTags
{
    protected function getMetaTitle(): string
    {
        $title = '';

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

            if (! empty($this->selectedPlatforms)) {
                $platforms = array_map(fn ($p) => ucfirst($this->decodeFilterValue($p)), $this->selectedPlatforms);
                $filters[] = 'for ' . implode('/', $platforms);
            }

            if (! empty($this->selectedStatuses)) {
                $statuses = array_map(fn ($s) => strtolower($this->decodeFilterValue($s)), $this->selectedStatuses);
                $filters[] = implode('/', $statuses);
            }

            if (! empty($this->selectedEngines)) {
                $engines = array_map(fn ($e) => $this->decodeFilterValue($e), $this->selectedEngines);
                $filters[] = 'made with ' . implode('/', $engines);
            }

            if (! empty($this->selectedLanguages)) {
                $languages = Language::whereIn('id', array_map([$this, 'decodeFilterValue'], $this->selectedLanguages))
                    ->pluck('ref_name')
                    ->implode('/');
                $filters[] = 'in ' . $languages;
            }

            if ($this->nsfw) {
                $filters[] = 'NSFW';
            }

            if ($this->search) {
                $filters[] = "matching '{$this->search}'";
            }

            if (! empty($filters)) {
                $title .= ', ' . implode(', ', $filters);
            }
        }

        return $title . ' - ' . config('app.name');
    }

    protected function getMetaDescription(): string
    {
        // For game list
        if (property_exists($this, 'games')) {
            $description = 'Browse';

            if ($this->nsfw) {
                $description .= ' NSFW';
            }

            if (! empty($this->selectedPlatforms)) {
                $platforms = array_map(fn ($p) => ucfirst($this->decodeFilterValue($p)), $this->selectedPlatforms);
                $description .= ' ' . implode('/', $platforms);
            }

            $description .= ' FVNs';

            if (! empty($this->selectedStatuses)) {
                $statuses = array_map(fn ($s) => strtolower($this->decodeFilterValue($s)), $this->selectedStatuses);
                $description .= ' that are ' . implode('/', $statuses);
            }

            if (! empty($this->selectedEngines)) {
                $engines = array_map(fn ($e) => $this->decodeFilterValue($e), $this->selectedEngines);
                $description .= ' created with ' . implode('/', $engines);
            }

            if (! empty($this->selectedLanguages)) {
                $languages = Language::whereIn('id', array_map([$this, 'decodeFilterValue'], $this->selectedLanguages))
                    ->pluck('ref_name')
                    ->implode('/');
                $description .= ' in ' . $languages;
            }

            if ($this->search) {
                $description .= " matching '{$this->search}'";
            }

            $description .= ". Featuring {$this->games->total()} titles";

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

        return asset('favicon.ico');
    }
}
