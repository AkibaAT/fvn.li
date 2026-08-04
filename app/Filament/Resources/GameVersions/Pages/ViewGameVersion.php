<?php

declare(strict_types=1);

namespace App\Filament\Resources\GameVersions\Pages;

use App\Filament\Resources\GameVersions\GameVersionResource;
use App\Filament\Resources\GameVersions\Traits\HandlesGameVersionLanguages;
use App\Models\GameVersion;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Log;

class ViewGameVersion extends ViewRecord
{
    use HandlesGameVersionLanguages;

    protected static string $resource = GameVersionResource::class;

    protected function mutateRecordDataBeforeFill(array $data): array
    {
        /** @var GameVersion $gameVersion */
        $gameVersion = $this->record;

        $languages = $this->loadSupportedLanguages($gameVersion);

        // Debug the languages
        Log::info('Languages for game version ' . $gameVersion->id, [
            'languages' => $languages,
        ]);

        $data['supported_languages'] = $languages;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            $this->getImportStatsAction(),
        ];
    }
}
