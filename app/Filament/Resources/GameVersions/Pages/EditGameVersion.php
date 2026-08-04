<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersions\Pages;

use App\Filament\Resources\GameVersions\GameVersionResource;
use App\Filament\Resources\GameVersions\Traits\HandlesGameVersionLanguages;
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

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var GameVersion $gameVersion */
        $gameVersion = $this->record;

        $data['supported_languages'] = $this->loadSupportedLanguages($gameVersion);

        return $data;
    }

    protected function afterSave(): void
    {
        /** @var GameVersion $gameVersion */
        $gameVersion = $this->record;

        if (isset($this->data['supported_languages']) && is_array($this->data['supported_languages'])) {
            $this->saveSupportedLanguages($gameVersion, $this->data['supported_languages']);
        }
    }
}
