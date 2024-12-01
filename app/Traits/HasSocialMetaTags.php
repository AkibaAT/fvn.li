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
            if ($this->getTableSearch())
            {
                $title .= " matching '{$this->getTableSearch()}'";
            }
        }

        return $title . ' - ' . config('app.name');
    }

    protected function getMetaDescription(): string
    {
        if (isset($this->record) && $this->record instanceof Model) {
            // For single record pages
            return $this->generateDescriptionFromRecord($this->record);
        }

        // For list pages
        if (method_exists($this, 'getModel')) {
            $modelClass = $this->getModel();
            $recentRecords = $modelClass::latest()->take(3)->get();
            return $this->generateListDescription($recentRecords);
        }

        return config('app.description', 'Default description');
    }

    protected function getMetaImage(): string
    {
        if (isset($this->record) && $this->record instanceof Model) {
            // If record has an image field, use it
            if (isset($this->record->image)) {
                return asset($this->record->image);
            }
        }

        // Default to your app's logo or a relevant image
        return asset('favicon.ico');
    }

    protected function generateTitleFromRecord(Model $record): string
    {
        // Example: "Product: MacBook Pro - Your App Name"
        $modelName = class_basename($record);
        $identifier = $record->title ?? $record->name ?? $record->id;
        return "{$modelName}: {$identifier} - " . config('app.name');
    }

    protected function generateDescriptionFromRecord(Model $record): string
    {
        $description = '';

        // Get the table's visible columns
        if (method_exists($this, 'getTableColumns')) {
            $columns = collect($this->getTableColumns())
                ->take(3) // Take first 3 columns
                ->map(function ($column) use ($record) {
                    $key = $column->getName();
                    $value = $record->{$key};
                    return "{$key}: {$value}";
                })
                ->join(', ');

            $description = $columns;
        } else {
            // Fallback to basic model information
            $description = "View details for {$this->getModelLabel()} #{$record->id}";
        }

        return Str::limit($description, 160);
    }

    protected function generateListDescription($records): string
    {
        $modelLabel = Str::plural($this->getModelLabel());
        $recordsList = $records->map(function ($record) {
            return $record->title ?? $record->name ?? "#{$record->id}";
        })->join(', ');

        return "Browse {$modelLabel}. Recent items include: {$recordsList}";
    }
}
