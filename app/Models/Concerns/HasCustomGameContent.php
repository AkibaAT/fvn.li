<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;

trait HasCustomGameContent
{
    public function getEffectiveName(bool $forceOriginal = false): ?string
    {
        // If forcing original view, always return itch.io content
        if ($forceOriginal) {
            return $this->name;
        }

        // If this game has custom page enabled and view_mode is set to original, show itch.io content
        if ($this->has_custom_page && $this->view_mode === 'original') {
            return $this->name;
        }

        // Show custom content if available
        return $this->has_custom_page && $this->custom_name
            ? $this->custom_name
            : $this->name;
    }

    public function getEffectiveDescription(bool $forceOriginal = false): ?string
    {
        // If forcing original view, always return itch.io content
        if ($forceOriginal) {
            return $this->full_description;
        }

        // If this game has custom page enabled and view_mode is set to original, show itch.io content
        if ($this->has_custom_page && $this->view_mode === 'original') {
            return $this->full_description;
        }

        // Show custom content if available
        return $this->has_custom_page && $this->custom_description
            ? $this->custom_description
            : $this->full_description;
    }

    public function getEffectiveScreenshots(bool $forceOriginal = false): array
    {
        // If forcing original view, always return itch.io screenshots
        if ($forceOriginal) {
            return $this->getScreenshots();
        }

        // If this game has custom page enabled and view_mode is set to original, show itch.io screenshots
        if ($this->has_custom_page && $this->view_mode === 'original') {
            return $this->getScreenshots();
        }

        return $this->has_custom_page && $this->custom_screenshots
            ? $this->resolveScreenshots($this->custom_screenshots)
            : $this->getScreenshots();
    }

    public function canUserEdit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return $user->is_admin || $user->ownsGame($this);
    }

    /**
     * Enable custom page editing and copy current data as baseline
     */
    public function enableCustomPage(User $user): void
    {
        $this->update([
            'has_custom_page' => true,
            'custom_description' => $this->full_description,
            'custom_screenshots' => $this->screenshots ?: [],
            'custom_assets' => [],
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ]);
    }

    /**
     * Disable custom page editing (revert to auto-sync)
     */
    public function disableCustomPage(): void
    {
        $this->update([
            'has_custom_page' => false,
            'custom_name' => null,
            'custom_description' => null,
            'custom_screenshots' => null,
            'custom_assets' => null,
            'custom_page_updated_at' => null,
            'custom_page_updated_by' => null,
        ]);
    }

    public function updateCustomPage(array $data, User $user): void
    {
        $updateData = [
            'custom_page_updated_at' => now(),
            'custom_page_updated_by' => $user->id,
        ];

        if (isset($data['name'])) {
            $updateData['custom_name'] = $data['name'];
        }

        if (isset($data['description'])) {
            $updateData['custom_description'] = $data['description'];
        }

        if (isset($data['screenshots'])) {
            $updateData['custom_screenshots'] = $data['screenshots'];
        }

        if (isset($data['assets'])) {
            $updateData['custom_assets'] = $data['assets'];
        }

        $this->update($updateData);
    }

    public function customPageUpdatedBy()
    {
        return $this->belongsTo(User::class, 'custom_page_updated_by');
    }

}
