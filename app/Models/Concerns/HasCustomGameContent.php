<?php

declare(strict_types=1);

namespace App\Models\Concerns;

use App\Models\User;

trait HasCustomGameContent
{
    /**
     * Get the effective name for display (custom or synced)
     */
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

    /**
     * Get the effective description for display (custom or synced)
     */
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

    /**
     * Get the effective screenshots for display (custom or synced)
     */
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
            ? $this->custom_screenshots
            : $this->getScreenshots();
    }

    /**
     * Check if the user can edit this game's custom page
     */
    public function canUserEdit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Admin can edit all games
        if ($user->is_admin) {
            return true;
        }

        // Check if user has explicit ownership permission
        if ($this->hasExplicitOwnership($user)) {
            return true;
        }

        // Check if user's itch.io account matches the game's namespace
        if ($this->hasItchIoOwnership($user)) {
            return true;
        }

        return false;
    }

    /**
     * Enable custom page editing and copy current data as baseline
     */
    public function enableCustomPage(User $user): void
    {
        $this->update([
            'has_custom_page' => true,
            'custom_name' => $this->name,
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

    /**
     * Update custom page content
     */
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

    /**
     * Check if user has explicit ownership through database relationship
     */
    private function hasExplicitOwnership(User $user): bool
    {
        // This could be implemented with a game_owners table in the future
        // For now, return false as we don't have this table
        return false;
    }

    /**
     * Check if user's itch.io account owns this game
     */
    private function hasItchIoOwnership(User $user): bool
    {
        // Use the existing ownsGame method from User model
        return $user->ownsGame($this);
    }
}
