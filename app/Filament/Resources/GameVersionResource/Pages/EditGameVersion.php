<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersionResource\Pages;

use App\Filament\Resources\GameVersionResource;
use App\Filament\Resources\GameVersionResource\Traits\HandlesGameVersionLanguages;
use App\Models\GameVersion;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditGameVersion extends EditRecord
{
    use HandlesGameVersionLanguages;

    protected static string $resource = GameVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            $this->getImportStatsAction(),
        ];
    }

    /**
     * Load the supported languages into the form data
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var GameVersion $gameVersion */
        $gameVersion = $this->record;

        // Load supported languages
        $data['supported_languages'] = $this->loadSupportedLanguages($gameVersion);

        return $data;
    }

    /**
     * Handle the supported languages after the record is saved
     */
    protected function afterSave(): void
    {
        /** @var GameVersion $gameVersion */
        $gameVersion = $this->record;

        // Save the supported languages
        if (isset($this->data['supported_languages']) && is_array($this->data['supported_languages'])) {
            $this->saveSupportedLanguages($gameVersion, $this->data['supported_languages']);
        }
    }
}
