<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Game;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

class GamePageEditor extends Component
{
    use WithFileUploads;

    public Game $game;
    public bool $editMode = false;
    public string $description = '';
    public array $uploadedFiles = [];
    public bool $showPreview = false;
    public string $activeTab = 'edit'; // 'edit' or 'preview'
    public string $message = '';
    public string $messageType = 'success';

    public function mount(Game $game): void
    {
        $this->game = $game;
        $this->description = $game->getEffectiveDescription() ?? '';
    }

    public function toggleEditMode(): void
    {
        if (! $this->canEdit()) {
            $this->addError('general', 'You do not have permission to edit this game page.');

            return;
        }

        $this->editMode = ! $this->editMode;

        if ($this->editMode && ! $this->game->has_custom_page) {
            // Enable custom page for first time
            $this->game->enableCustomPage(Auth::user());
            $this->description = $this->game->custom_description ?? '';
        }

        if ($this->editMode) {
            // Dispatch event to initialize TinyMCE when entering edit mode
            $this->dispatch('initializeTinyMCE');
        }

        if (! $this->editMode) {
            // Reset any unsaved changes
            $this->description = $this->game->getEffectiveDescription() ?? '';
            $this->uploadedFiles = [];
            $this->resetErrorBag();
        }
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;

        // Dispatch event to ensure TinyMCE is initialized
        $this->dispatch('initializeTinyMCE');
    }

    public function switchTab(string $tab): void
    {
        if (in_array($tab, ['edit', 'preview'])) {
            $this->activeTab = $tab;

            if ($tab === 'edit') {
                // Ensure TinyMCE is initialized when switching to edit tab
                $this->dispatch('initializeTinyMCE');
            }
        }
    }

    public function save(): void
    {
        if (! $this->canEdit()) {
            $this->addError('general', 'You do not have permission to edit this game page.');

            return;
        }

        $this->validate([
            'description' => 'nullable|string|max:50000',
            'uploadedFiles.*' => 'nullable|image|max:10240', // 10MB per file
        ]);

        try {
            // Process uploaded files if any
            if (! empty($this->uploadedFiles)) {
                foreach ($this->uploadedFiles as $file) {
                    $filename = uniqid() . '.' . $file->getClientOriginalExtension();
                    $file->storeAs("editor-uploads/{$this->game->id}", $filename, 'public');
                }
                $this->uploadedFiles = [];
            }

            // Update the game with new content
            $this->game->updateCustomPage([
                'description' => $this->description,
            ], Auth::user());

            $this->editMode = false;
            $this->dispatch('exitEditMode');
            $this->showMessage('Game page updated successfully!', 'success');

            // Refresh the component data
            $this->description = $this->game->getEffectiveDescription() ?? '';

        } catch (Exception $e) {
            $this->showMessage('Error saving changes: ' . $e->getMessage(), 'error');
        }
    }

    public function cancel(): void
    {
        $this->editMode = false;
        $this->dispatch('exitEditMode');
        $this->showPreview = false;
        $this->description = $this->game->getEffectiveDescription() ?? '';
        $this->uploadedFiles = [];
        $this->resetErrorBag();
    }

    public function enableCustomPage(): void
    {
        if (! $this->canEdit()) {
            $this->addError('general', 'You do not have permission to edit this game page.');

            return;
        }

        $this->game->enableCustomPage(Auth::user());
        $this->showMessage('Custom page editing enabled! Auto-sync from itch.io is now disabled.', 'success');
    }

    public function disableCustomPage(): void
    {
        if (! $this->canEdit()) {
            $this->addError('general', 'You do not have permission to edit this game page.');

            return;
        }

        $this->game->disableCustomPage();

        // Refresh data to show itch.io content
        $this->description = $this->game->getEffectiveDescription() ?? '';

        $this->showMessage('Custom page editing disabled! Auto-sync from itch.io is now enabled.', 'success');
    }

    public function getDescriptionPreviewProperty(): string
    {
        // Process the description for preview (you might want to sanitize HTML here)
        return $this->description;
    }

    public function canEdit(): bool
    {
        $user = Auth::user();

        return $user && $this->game->canUserEdit($user);
    }

    public function getIsCustomPageProperty(): bool
    {
        return $this->game->has_custom_page;
    }

    public function getLastUpdatedProperty(): ?string
    {
        if (! $this->game->custom_page_updated_at) {
            return null;
        }

        return $this->game->custom_page_updated_at->diffForHumans();
    }

    public function getUpdatedByProperty(): ?User
    {
        return $this->game->customPageUpdatedBy;
    }

    public function clearMessage(): void
    {
        $this->message = '';
    }

    public function render()
    {
        return view('livewire.game-page-editor');
    }

    private function showMessage(string $message, string $type = 'success'): void
    {
        $this->message = $message;
        $this->messageType = $type;

        // Clear message after 5 seconds
        $this->dispatch('clearMessage');
    }
}
