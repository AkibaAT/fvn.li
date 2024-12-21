<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
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

            if (!empty($this->selectedPlatforms)) {
                $platforms = array_map(fn($p) => ucfirst($this->decodeFilterValue($p)), $this->selectedPlatforms);
                $filters[] = 'for ' . implode('/', $platforms);
            }

            if (!empty($this->selectedStatuses)) {
                $statuses = array_map(fn($s) => strtolower($this->decodeFilterValue($s)), $this->selectedStatuses);
                $filters[] = implode('/', $statuses);
            }

            if (!empty($this->selectedEngines)) {
                $engines = array_map(fn($e) => $this->decodeFilterValue($e), $this->selectedEngines);
                $filters[] = 'made with ' . implode('/', $engines);
            }

            if ($this->nsfw) {
                $filters[] = 'NSFW';
            }

            if ($this->search) {
                $filters[] = "matching '{$this->search}'";
            }

            if (!empty($filters)) {
                $title .= ', ' . implode(', ', $filters);
            }
        }

        return $title . ' - ' . config('app.name');
    }

    protected function getMetaDescription(): string
    {
        // For game list
        if (property_exists($this, 'games')) {
            $description = "Browse";

            if ($this->nsfw) {
                $description .= ' NSFW';
            }

            if (!empty($this->selectedPlatforms)) {
                $platforms = array_map(fn($p) => ucfirst($this->decodeFilterValue($p)), $this->selectedPlatforms);
                $description .= ' ' . implode('/', $platforms);
            }

            $description .= " FVNs";

            if (!empty($this->selectedStatuses)) {
                $statuses = array_map(fn($s) => strtolower($this->decodeFilterValue($s)), $this->selectedStatuses);
                $description .= ' that are ' . implode('/', $statuses);
            }

            if (!empty($this->selectedEngines)) {
                $engines = array_map(fn($e) => $this->decodeFilterValue($e), $this->selectedEngines);
                $description .= ' created with ' . implode('/', $engines);
            }

            if ($this->search) {
                $description .= " matching '{$this->search}'";
            }

            $description .= ". Featuring {$this->games->total()} titles";

            // Add sort information if not default
            if (property_exists($this, 'sortField') &&
                ($this->sortField !== 'version_published_at' || $this->sortDirection !== 'desc')) {
                $sortLabels = [
                    'version_published_at' => 'latest update',
                    'initially_published_at' => 'initial release',
                    'stats_words' => 'word count',
                    'rating_count' => 'review count',
                    'name' => 'name'
                ];

                $description .= ", sorted by {$sortLabels[$this->sortField]} " .
                    ($this->sortDirection === 'asc' ? 'ascending' : 'descending');
            }

            $description .= '.';

            return $description;
        }

        // For single record pages
        if (isset($this->record) && $this->record instanceof Model) {
            return $this->generateDescriptionFromRecord($this->record);
        }

        return config('app.description', 'Default description');
    }

    protected function getMetaImage(): string
    {
        if (property_exists($this, 'games') && $this->games->count() > 0) {
            return $this->games->get(0)->thumb_url;
        }

        return asset('favicon.ico');
    }
}
