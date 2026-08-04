<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use Illuminate\Support\Collection;

trait HasGameLanguageSupport
{
    public function getSupportedLanguages(): Collection
    {
        if ($this->relationLoaded('latestVersion') &&
            $this->latestVersion?->relationLoaded('supportedLanguages')) {
            return $this->latestVersion->supportedLanguages
                ->where('is_available', true)  // Only include available languages
                ->whereNotNull('language')  // Exclude stats without valid language records
                ->where(function ($stat) {
                    return ! str_starts_with($stat->iso_code, 'q');  // Exclude placeholder codes
                })
                ->map(fn ($stat) => [
                    'iso_code' => $stat->iso_code,
                    'ref_name' => $stat->language->ref_name,
                    'flag_code' => $stat->language->flag_code,
                ])->collect();
        }

        return collect();
    }

    public function getAllSupportedLanguages(): Collection
    {
        if ($this->relationLoaded('latestVersion') &&
            $this->latestVersion?->relationLoaded('supportedLanguages')) {
            return $this->latestVersion->supportedLanguages
                ->whereNotNull('language')  // Exclude stats without valid language records
                ->where(function ($stat) {
                    return ! str_starts_with($stat->iso_code, 'q');  // Exclude placeholder codes
                })
                ->map(fn ($stat) => [
                    'iso_code' => $stat->iso_code,
                    'ref_name' => $stat->language->ref_name,
                    'flag_code' => $stat->language->flag_code,
                    'is_available' => $stat->is_available,
                ])->collect();
        }

        return collect();
    }

    public function getAvailableLanguages(): Collection
    {
        if (! $this->latestVersion) {
            return collect();
        }

        return $this->latestVersion->supportedLanguages()
            ->where('is_available', true)
            ->with('language')
            ->get()
            ->map(fn ($sl) => [
                'iso_code' => $sl->iso_code,
                'ref_name' => $sl->language->ref_name,
                'flag_code' => $sl->language->flag_code,
            ]);
    }

    public function isLanguageAvailable(string $isoCode): bool
    {
        if (! $this->latestVersion) {
            return false;
        }

        $support = $this->latestVersion->supportedLanguages()
            ->where('iso_code', $isoCode)
            ->first();

        return $support && $support->is_available;
    }

    public function getEnglishWordCount(): ?int
    {
        if (isset($this->attributes['english_word_count'])) {
            return $this->english_word_count;
        }

        // Otherwise load from the latest version
        if ($this->relationLoaded('latestVersion')) {
            $englishStats = $this->latestVersion?->getStatsForLanguage('eng');

            return $englishStats?->words;
        }

        return null;
    }

    public function getPrimaryWordCount(): ?int
    {
        $langCode = $this->source_language_id ?? 'eng';

        // If it's English, delegate to existing method
        if ($langCode === 'eng') {
            return $this->getEnglishWordCount();
        }

        if ($this->relationLoaded('latestVersion') && $this->latestVersion) {
            if ($this->latestVersion->relationLoaded('languageStats')) {
                $stats = $this->latestVersion->languageStats
                    ->where('iso_code', $langCode)
                    ->first();

                return $stats?->words;
            }

            $stats = $this->latestVersion->getStatsForLanguage($langCode);

            return $stats?->words;
        }

        return null;
    }

    public function getPrimaryLanguageLabel(): string
    {
        $langCode = $this->source_language_id ?? 'eng';

        if ($this->relationLoaded('sourceLanguage') && $this->sourceLanguage) {
            $part1 = $this->sourceLanguage->part1;
            if ($part1) {
                return strtoupper($part1);
            }
        }

        // Fallback: uppercase first 2 chars of ISO code
        return strtoupper(substr($langCode, 0, 2));
    }

    public function getLatestCharacterStats(string $isoCode): Collection
    {
        return $this->latestVersion?->getCharacterStatsForLanguage($isoCode) ?? collect();
    }
}
